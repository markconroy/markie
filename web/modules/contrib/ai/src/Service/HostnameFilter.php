<?php

namespace Drupal\ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\ai\Dto\HostnameFilterDto;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\MarkdownParser;
use Masterminds\HTML5;

/**
 * Service to filter hostnames and find them in images and links.
 */
class HostnameFilter {

  /**
   * The allowed domain names.
   *
   * @var array|null
   */
  protected ?array $allowedDomains = NULL;

  /**
   * Whether to rewrite disallowed links (NULL means use settings).
   *
   * @var bool|null
   */
  protected ?bool $rewriteLinks = NULL;

  /**
   * Whether full trust mode is enabled (NULL means use settings).
   *
   * @var bool|null
   */
  protected ?bool $fullTrust = NULL;

  /**
   * Whether to use plain text mode for URL filtering.
   *
   * When enabled, treats all input as plain text and filters URLs
   * based on white list, regardless of HTML/Markdown markup.
   *
   * @var bool|null
   */
  protected ?bool $plainTextMode = NULL;

  /**
   * Whether the consumer renders Markdown rather than HTML.
   */
  protected bool $markdownMode = FALSE;

  /**
   * Whether the filtered text is known to be a JSON payload.
   *
   * JSON payloads are auto-detected regardless of this flag; setting it
   * additionally guarantees that a payload that fails to decode (malformed
   * or truncated JSON) is filtered as plain text instead of being pushed
   * through the HTML round trip, which would corrupt its structure.
   *
   * @var bool|null
   */
  protected ?bool $jsonMode = NULL;

  /**
   * HTML5 parser instance.
   *
   * @var \Masterminds\HTML5
   */
  private HTML5 $html5;

  /**
   * Markdown parser used to distinguish HTML from code and autolinks.
   */
  private ?MarkdownParser $markdownParser = NULL;

  /**
   * URL-bearing attributes by tag.
   *
   * @var array<string, string[]>
   */
  protected array $urlAttributes = [
    'a' => ['href', 'ping', 'xlink:href'],
    'area' => ['href', 'ping'],
    'img' => ['src', 'srcset', 'data-src', 'data-srcset'],
    'image' => ['href', 'xlink:href'],
    'source' => ['src', 'srcset', 'data-src', 'data-srcset'],
    'video' => ['src', 'data-src', 'poster'],
    'audio' => ['src', 'data-src'],
    'track' => ['src', 'data-src'],
    'iframe' => ['src', 'data-src'],
    'embed' => ['src', 'data-src'],
    'object' => ['data'],
    'link' => ['href', 'imagesrcset'],
    'script' => ['src', 'data-src'],
    'form' => ['action'],
    'button' => ['formaction'],
    'input' => ['src', 'formaction'],
    'use' => ['href', 'xlink:href'],
    'base' => ['href'],
    'meta' => ['content'],
  ];

  /**
   * Attributes that contain multiple space/comma-separated URLs.
   *
   * @var string[]
   */
  protected array $multiUrlAttributes = ['srcset', 'data-srcset', 'ping', 'imagesrcset'];

  /**
   * Constructs a HostnameFilter object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected ConfigFactoryInterface $configFactory,
  ) {
    $this->html5 = new HTML5([
      'disable_html_ns' => TRUE,
    ]);
  }

  /**
   * Filter text by removing links/images with disallowed hostnames.
   *
   * @param string $text
   *   The text to filter (can contain HTML and/or Markdown).
   *
   * @return string
   *   The filtered text.
   */
  public function filterText(string $text): string {
    // If full trust is enabled, return text as-is.
    if ($this->isFullTrustEnabled()) {
      return $text;
    }

    // If plain text mode is enabled, filter as plain text.
    if ($this->plainTextMode) {
      // Resolve disallowed Markdown links and images as a whole first, so the
      // URL pattern below does not leave broken link syntax like "[text](".
      return $this->filterUrlsInPlainText($this->filterMarkdownLinks($text, FALSE, TRUE));
    }

    // JSON payloads (structured output, prompt-engineered JSON, JSON tool
    // arguments) must keep their structure: filter their string values
    // individually instead of pushing the whole payload through the HTML5
    // round trip below, which would entity-encode it beyond decoding.
    $json_result = $this->filterJsonText($text);
    if ($json_result !== NULL) {
      return $json_result;
    }

    // The caller declared the text a JSON payload but it did not decode
    // (malformed, truncated or a partial stream chunk).
    if ($this->jsonMode) {
      return $this->filterJsonFragment($text);
    }

    // Filter Markdown syntax first.
    $text = $this->filterMarkdownLinks($text);

    // Text without any markup has no HTML to filter. Return it as is, because
    // the round trip through the HTML serializer would encode characters like
    // "&" and ">" and thereby break Markdown block quotes, code and tables.
    if (!$this->containsHtmlMarkup($text)) {
      return $text;
    }

    // Filter HTML tags.
    $dom = $this->html5->loadHTMLFragment($text);
    $this->filterHtmlLinks($dom);
    $potentially_sanitized = $this->html5->saveHTML($dom);
    if ($potentially_sanitized !== $text) {
      // Only update if changes were made and HTML existed.
      $text = $potentially_sanitized;
    }

    // Return the filtered text.
    return $text;
  }

  /**
   * Set allowed domain names.
   *
   * @param array $domains
   *   Array of allowed domain names (can include wildcards like *.example.com).
   */
  public function setAllowedDomains(array $domains): void {
    $this->allowedDomains = $domains;
  }

  /**
   * Set whether to rewrite disallowed links.
   *
   * @param bool $rewrite
   *   TRUE to rewrite disallowed links to show the URL, FALSE to remove them.
   */
  public function setRewriteLinks(bool $rewrite): void {
    $this->rewriteLinks = $rewrite;
  }

  /**
   * Set whether full trust mode is enabled.
   *
   * @param bool $full_trust
   *   TRUE to bypass all filtering, FALSE to apply filtering.
   */
  public function setFullTrust(bool $full_trust): void {
    $this->fullTrust = $full_trust;
  }

  /**
   * Set whether to use plain text mode for URL filtering.
   *
   * @param bool $plain_text_mode
   *   TRUE to filter URLs as plain text (ignores markup),
   *   FALSE to apply format-aware filtering (HTML/Markdown).
   */
  public function setPlainTextMode(bool $plain_text_mode): void {
    $this->plainTextMode = $plain_text_mode;
  }

  /**
   * Enables Markdown code preservation for consumers that render Markdown.
   *
   * @param bool $markdown_mode
   *   TRUE only when the filtered output will be rendered as Markdown.
   */
  public function setMarkdownMode(bool $markdown_mode): void {
    $this->markdownMode = $markdown_mode;
  }

  /**
   * Set whether the filtered text is known to be a JSON payload.
   *
   * @param bool $json_mode
   *   TRUE to treat undecodable payloads as plain text instead of HTML,
   *   FALSE to use the regular pipeline for them. Decodable JSON payloads
   *   are auto-detected and structure-preserved regardless of this flag.
   */
  public function setJsonMode(bool $json_mode): void {
    $this->jsonMode = $json_mode;
  }

  /**
   * Apply settings from a HostnameFilterDto.
   *
   * This allows programmatic override of settings.php configuration.
   *
   * @param \Drupal\ai\Dto\HostnameFilterDto $dto
   *   The DTO containing filter settings.
   */
  public function applySettings(HostnameFilterDto $dto): void {
    if ($dto->allowedDomainNames !== NULL) {
      $this->setAllowedDomains($dto->allowedDomainNames);
    }
    if ($dto->rewriteLinks !== NULL) {
      $this->setRewriteLinks($dto->rewriteLinks);
    }
    if ($dto->fullTrust !== NULL) {
      $this->setFullTrust($dto->fullTrust);
    }
    if ($dto->markdownMode !== NULL) {
      $this->setMarkdownMode($dto->markdownMode);
    }
    if ($dto->plainTextMode !== NULL) {
      $this->setPlainTextMode($dto->plainTextMode);
    }
    if ($dto->jsonMode !== NULL) {
      $this->setJsonMode($dto->jsonMode);
    }
  }

  /**
   * Capture current programmatic overrides so they can later be restored.
   *
   * The HostnameFilter service is a singleton; callers that temporarily
   * override its state (e.g. to scope a HostnameFilterDto to a single
   * provider call) should snapshot before applying and restore afterwards
   * to avoid leaking settings into unrelated calls.
   *
   * @return array
   *   Opaque snapshot to pass to ::restoreSettings().
   */
  public function snapshotSettings(): array {
    return [
      'allowedDomains' => $this->allowedDomains,
      'rewriteLinks' => $this->rewriteLinks,
      'fullTrust' => $this->fullTrust,
      'plainTextMode' => $this->plainTextMode,
      'markdownMode' => $this->markdownMode,
      'jsonMode' => $this->jsonMode,
    ];
  }

  /**
   * Restore a snapshot taken with ::snapshotSettings().
   *
   * @param array $snapshot
   *   The snapshot returned by ::snapshotSettings().
   */
  public function restoreSettings(array $snapshot): void {
    $this->allowedDomains = $snapshot['allowedDomains'] ?? NULL;
    $this->rewriteLinks = $snapshot['rewriteLinks'] ?? NULL;
    $this->fullTrust = $snapshot['fullTrust'] ?? NULL;
    $this->plainTextMode = $snapshot['plainTextMode'] ?? NULL;
    $this->markdownMode = $snapshot['markdownMode'] ?? FALSE;
    $this->jsonMode = $snapshot['jsonMode'] ?? NULL;
  }

  /**
   * Get allowed domain names.
   *
   * @return array
   *   Array of allowed domain names.
   */
  public function getAllowedDomains(): array {
    // Return cached value if available.
    if ($this->allowedDomains !== NULL) {
      return $this->allowedDomains;
    }

    // Fetch from config.
    $config = $this->configFactory->get('ai.settings');
    $allowed_domains = $config->get('allowed_hosts');

    if (is_string($allowed_domains)) {
      $allowed_domains = [$allowed_domains];
    }

    if (!is_array($allowed_domains)) {
      $this->allowedDomains = [];
      return $this->allowedDomains;
    }

    $this->allowedDomains = array_values(array_filter(
      array_map(
        static fn($host): string => trim((string) $host),
        $allowed_domains
      ),
      static fn(string $host): bool => $host !== ''
    ));

    return $this->allowedDomains;
  }

  /**
   * Check if full trust mode is enabled.
   *
   * @return bool
   *   TRUE if all domains are allowed, FALSE otherwise.
   */
  protected function isFullTrustEnabled(): bool {
    // Return cached value if available.
    if ($this->fullTrust !== NULL) {
      return $this->fullTrust;
    }

    $settings = Settings::get('ai_output', []);
    return !empty($settings['full_trust_mode']);
  }

  /**
   * Check if link rewriting mode is enabled.
   *
   * @return bool
   *   TRUE if disallowed links should be rewritten to show the URL,
   *   FALSE to remove them.
   */
  protected function shouldRewriteLinks(): bool {
    // Return cached value if available.
    if ($this->rewriteLinks !== NULL) {
      return $this->rewriteLinks;
    }

    // Fetch from config.
    $config = $this->configFactory->get('ai.settings');
    return !empty($config->get('allowed_hosts_rewrite_links'));
  }

  /**
   * Filter URLs in plain text mode.
   *
   * This treats all input as plain text and removes URLs that don't
   * match the white list, regardless of HTML or Markdown markup.
   *
   * @param string $input
   *   The text to filter.
   *
   * @return string
   *   The filtered text with disallowed URLs removed.
   */
  protected function filterUrlsInPlainText(string $input): string {
    // Dangerous (hostless) scheme URIs such as javascript: and data: have no
    // host to match against the allowlist, so the host-based pattern below
    // cannot catch them. Strip them here. The scheme may be obfuscated with
    // HTML entities or ASCII whitespace (e.g. "java&#9;script:"), so decode and
    // normalize each candidate the way a browser does before deciding, and act
    // only on a known denylist so prose like "Note:" is left untouched.
    $dangerousSchemes = ['javascript', 'data', 'vbscript'];
    $schemePattern = '/[a-z](?:[a-z0-9+.\-]|&#[0-9a-fx]+;|[ \t\r\n])*:[^\s"\'<>]+/i';
    $input = preg_replace_callback($schemePattern, function ($matches) use ($dangerousSchemes) {
      $decoded = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML5);
      $decoded = (string) preg_replace('/[\x09\x0A\x0D]/', '', $decoded);
      $scheme = strtolower((string) parse_url($decoded, PHP_URL_SCHEME));
      if (in_array($scheme, $dangerousSchemes, TRUE)) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed URL: @url',
          ['@url' => $matches[0]]
        );
        // A trailing backslash starts an escape sequence that follows the
        // URI, such as the \" that closes an attribute in a JSON string.
        return substr($matches[0], strlen(rtrim($matches[0], '\\')));
      }
      return $matches[0];
    }, $input);

    // Regex to match URLs like:
    // https://example.com/path
    // http://example.com
    // www.example.com
    // example.com/path
    // Stop at whitespace, quotes, or angle brackets to avoid breaking HTML.
    // Browsers treat "\" as "/", so match backslash variants of "//" too.
    // The bare protocol-relative alternative must start at a boundary (not in
    // the middle of a token or a longer run of slashes) and is only treated as
    // a URL when it has a host-like authority, see ::isProtocolRelativeHost().
    // The scheme is matched case-insensitively, as browsers do ("HTTPS://").
    $urlPattern = '/(https?:[\/\\\\]{2,}[^\s"\'<>]+|(?<![\w.\-\/\\\\])[\/\\\\]{2,}[^\s"\'<>]+|www\.[^\s"\'<>]+|(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,6}(?:\/[^\s"\'<>]*)?)/i';
    // Find all URLs and check if they match the allowed domains.
    $output = preg_replace_callback($urlPattern, function ($matches) {
      $url = $matches[0];
      // Escape sequences in JSON ("\/node\/1"), regular expressions or
      // escaped strings ("\\n") also start with slashes or backslashes. Only
      // treat them as a URL if they could be read as "//host.tld". Candidates
      // without a backslash are always checked.
      if (preg_match('/^[\/\\\\]/', $url) && !$this->isProtocolRelativeHost($url)) {
        return $url;
      }
      // Trailing punctuation is not part of the URL, but part of the text
      // around it, like the end of a sentence or of a Markdown link. The same
      // goes for a trailing backslash: it starts an escape sequence, such as
      // the \" that closes an attribute in a JSON string.
      $url = rtrim($url, '.,!?;:\'")\\');
      $trailing = substr($matches[0], strlen($url));
      if (!$this->isUrlAllowed($url)) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed URL: @url',
          ['@url' => $url]
        );
        return $trailing;
      }
      // Keep the original text, including any trailing punctuation.
      return $matches[0];
    }, $input);
    return $output;
  }

  /**
   * Checks for HTML markup, respecting the consumer's rendering mode.
   *
   * In Markdown mode, autolinks, reference destinations and code can have angle
   * brackets without being HTML. Parse Markdown without rendering it so those
   * constructs do not trigger an HTML round trip. Check ordinary text too,
   * since browsers can interpret malformed tags that Markdown treats as text.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE if the text contains HTML markup, FALSE if it is text only.
   */
  protected function containsHtmlMarkup(string $text): bool {
    // The HTML parser drops invalid UTF-8 bytes before tokenizing, so
    // "<\x80img" is a tag to the parser although the checks below see none.
    // Such text takes the HTML path. This also keeps it away from the Markdown
    // parser, which rejects invalid UTF-8 with an exception.
    if (!mb_check_encoding($text, 'UTF-8')) {
      return TRUE;
    }

    if (preg_match('/<[a-z\/!?]/i', $text) !== 1) {
      return FALSE;
    }

    // HTML consumers treat tags inside backticks or indented text as markup.
    // Only exempt Markdown code when the caller explicitly selects Markdown.
    if (!$this->markdownMode) {
      return TRUE;
    }

    if ($this->markdownParser === NULL) {
      $environment = new Environment();
      $environment->addExtension(new CommonMarkCoreExtension());
      $this->markdownParser = new MarkdownParser($environment);
    }
    $walker = $this->markdownParser->parse($text)->walker();
    while ($event = $walker->next()) {
      $node = $event->getNode();
      if ($node instanceof HtmlBlock || $node instanceof HtmlInline) {
        return TRUE;
      }
      if ($node instanceof Text && preg_match('/<[a-z\/!?]/i', $node->getLiteral()) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Checks whether a slash/backslash-led string has a host-like authority.
   *
   * Browsers resolve "//host", "\\host", "/\host" and "\/host" to a host, so
   * these must be checked against the allowlist. Escape sequences such as
   * "\\n" or "\/node\/1" yield a single-label "host" without a dot, which is
   * not a hostname worth filtering. Only candidates containing a backslash can
   * be such escape sequences, so a plain "//host" always counts as a host.
   *
   * @param string $candidate
   *   The matched text, starting with a slash or backslash.
   *
   * @return bool
   *   TRUE if the authority is a dotted hostname, localhost, an IP literal
   *   (including integer and hexadecimal IPv4), or a non-ASCII (IDN) name.
   */
  protected function isProtocolRelativeHost(string $candidate): bool {
    if (!str_contains($candidate, '\\')) {
      return TRUE;
    }
    $normalized = '//' . ltrim(str_replace('\\', '/', $candidate), '/');
    $host = parse_url($normalized, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
      return FALSE;
    }
    return str_contains($host, '.')
      || str_starts_with($host, '[')
      || strtolower($host) === 'localhost'
      // Browsers read "//1572395042" and "//0x7f000001" as IPv4 addresses.
      // Short digit runs are left alone: they are regex back-references.
      || preg_match('/^(?:\d{4,}|0x[0-9a-f]+)$/i', $host) === 1
      // IDN full stops (e.g. U+FF0E) are normalized to dots by browsers.
      || preg_match('/[^\x00-\x7F]/', $host) === 1;
  }

  /**
   * Filter HTML links and images.
   *
   * @param \DOMNode $node
   *   The DOM node to process.
   *
   * @return bool
   *   TRUE if this node or any descendant was removed or rewritten, FALSE
   *   if the tree passed through untouched.
   */
  protected function filterHtmlLinks(\DOMNode $node): bool {
    $modified = FALSE;
    if ($node instanceof \DOMElement) {
      $tag = strtolower($node->tagName);

      // Remove dangerous auto-loading elements entirely - if you are doing a
      // code assistant, you will have to opt out of filtering specifically.
      if (in_array($tag, ['script', 'iframe', 'embed', 'object', 'style'], TRUE)) {
        $node->parentNode?->removeChild($node);
        return TRUE;
      }

      // Strip event handler attributes (on* attributes) and HTMX attributes
      // (hx-* and data-hx-*).
      $attrsToRemove = [];
      foreach ($node->attributes as $attribute) {
        $attrName = strtolower($attribute->name);
        if (str_starts_with($attrName, 'on')
          || str_starts_with($attrName, 'hx')
          || str_starts_with($attrName, 'data-hx')) {
          $attrsToRemove[] = $attribute->name;
        }
      }
      foreach ($attrsToRemove as $attrName) {
        $node->removeAttribute($attrName);
        $modified = TRUE;
      }

      // Meta refresh redirects encode the destination as "<delay>;url=<url>"
      // in the content attribute, which is not a plain URL. Extract and check
      // the destination so it cannot redirect to a disallowed host.
      if ($tag === 'meta'
        && strtolower($node->getAttribute('http-equiv')) === 'refresh'
        && $node->hasAttribute('content')) {
        $content = $node->getAttribute('content');
        if (preg_match('/url\s*=\s*(.+)$/i', $content, $refreshMatches)) {
          $refreshUrl = trim($refreshMatches[1], " \t\n\r\0\x0B\"'");
          if (!$this->isUrlAllowed($refreshUrl)) {
            $this->loggerFactory->get('ai')->warning(
              'Removed disallowed AI output meta refresh URL: @url',
              ['@url' => $refreshUrl]
            );
            $node->removeAttribute('content');
            $modified = TRUE;
          }
        }
      }

      if (isset($this->urlAttributes[$tag])) {
        foreach ($this->urlAttributes[$tag] as $attr) {
          if (!$node->hasAttribute($attr)) {
            continue;
          }

          $url = $node->getAttribute($attr);

          // Handle multi-URL attributes (srcset, ping) separately.
          if (in_array($attr, $this->multiUrlAttributes, TRUE)) {
            if (!$this->isMultiUrlAttributeAllowed($url)) {
              $this->loggerFactory->get('ai')->warning(
                'Removed disallowed AI output multi-URL attribute @attr: @url',
                ['@attr' => $attr, '@url' => $url]
              );
              $node->removeAttribute($attr);
              $modified = TRUE;
            }
            continue;
          }

          if (!$this->isUrlAllowed($url)) {
            if ($this->shouldRewriteLinks() && $tag === 'a') {
              $this->loggerFactory->get('ai')->warning(
                'Rewriting disallowed AI output URL: @url',
                ['@url' => $url]
              );
              $node->nodeValue = $url;
            }
            else {
              $this->loggerFactory->get('ai')->warning(
                'Removed disallowed AI output URL: @url',
                ['@url' => $url]
              );
              $node->removeAttribute($attr);
            }
            $modified = TRUE;
          }
        }
      }

      // Check background attribute on any element.
      if ($node->hasAttribute('background')) {
        $bgUrl = $node->getAttribute('background');
        if (!$this->isUrlAllowed($bgUrl)) {
          $node->removeAttribute('background');
          $modified = TRUE;
        }
      }

      // Strip inline CSS url().
      if ($node->hasAttribute('style') && stripos($node->getAttribute('style'), 'url(') !== FALSE) {
        $node->removeAttribute('style');
        $modified = TRUE;
      }
    }

    // Avoid live NodeList mutation issues.
    $children = [];
    foreach ($node->childNodes as $child) {
      $children[] = $child;
    }

    foreach ($children as $child) {
      // The recursive call must run first so || cannot short-circuit it.
      $modified = $this->filterHtmlLinks($child) || $modified;
    }

    return $modified;
  }

  /**
   * Filter a JSON payload without corrupting its structure.
   *
   * Structured output responses (and prompt-engineered JSON) must never be
   * pushed through the HTML5 round trip in ::filterText(): the serializer
   * entity-encodes characters that are meaningful in JSON and the payload
   * stops being decodable. Instead, every string in the payload (values and
   * keys) is filtered on its own and only the strings that change are
   * rewritten. A clean payload is returned byte-for-byte.
   *
   * Two payload shapes are recognized: text that is wholly a JSON object or
   * array (surrounding whitespace allowed), and a single fenced code block
   * (```json with optional surrounding prose, or a bare ``` fence surrounded
   * only by whitespace) whose content is a JSON object or array.
   *
   * @param string $text
   *   The text that may be a JSON payload.
   * @param int $depth
   *   Current recursion depth for nested JSON-in-string values.
   *
   * @return string|null
   *   The filtered text, or NULL when the text is not a recognizable JSON
   *   payload and the caller should fall through to the regular pipeline.
   */
  protected function filterJsonText(string $text, int $depth = 0): ?string {
    if ($depth > 8) {
      return NULL;
    }

    $trimmed = trim($text);
    if ($trimmed === '') {
      return NULL;
    }

    // Whole-payload JSON object or array. Scalar payloads ("text", 5, true)
    // are deliberately not treated as JSON: they are indistinguishable from
    // prose and carry no structure to preserve.
    if ($trimmed[0] === '{' || $trimmed[0] === '[') {
      $decoded = json_decode($trimmed);
      if (is_array($decoded) || is_object($decoded)) {
        return $this->filterJsonPayload($text, $depth);
      }
    }

    return $this->filterFencedJsonText($text, $depth);
  }

  /**
   * Filter the strings of a valid JSON payload and check the result.
   *
   * The strings are checked decoded, but the raw text is what gets returned.
   * JSON escapes (\" or \u0027) can hide an attribute from the decoded check
   * that an HTML parser reading the raw text sees, and Markdown can form
   * across two strings (an autolink that opens in one and closes in the
   * next). When the filtered payload still reads as unsafe, "<", ">", "&" and
   * quotes are escaped as \u sequences instead. Both forms decode to the same
   * data. Escaping cannot break a raw line that reads as a Markdown reference
   * definition; such a payload is left to the regular pipeline.
   *
   * @param string $json
   *   Valid JSON text, surrounding whitespace allowed.
   * @param int $depth
   *   Current recursion depth for nested JSON-in-string values.
   *
   * @return string|null
   *   The filtered payload, or NULL when it cannot be made safe as JSON and
   *   the caller should fall through to the regular pipeline.
   */
  protected function filterJsonPayload(string $json, int $depth): ?string {
    foreach ([FALSE, TRUE] as $escape) {
      $filtered = $this->filterJsonStrings($json, $depth, $escape);
      if ($filtered === NULL) {
        return NULL;
      }
      if (!$this->hasUnsafeMarkup($filtered)) {
        return $filtered;
      }
    }
    return NULL;
  }

  /**
   * Filter every string of a valid JSON text in place.
   *
   * Valid JSON has no double quote outside a string, so the pattern matches
   * exactly the strings, keys included. Only the strings that filtering
   * changed are rewritten: numbers (also the ones PHP cannot represent),
   * whitespace and duplicate keys are returned as they came in.
   *
   * @param string $json
   *   Valid JSON text.
   * @param int $depth
   *   Current recursion depth for nested JSON-in-string values.
   * @param bool $escape
   *   TRUE to rewrite every string with "<", ">", "&" and quotes as \u
   *   sequences, FALSE to rewrite only the strings that filtering changed.
   *
   * @return string|null
   *   The filtered JSON text, or NULL when a string could not be processed.
   */
  protected function filterJsonStrings(string $json, int $depth, bool $escape): ?string {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($escape) {
      $flags |= JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    }
    $failed = FALSE;
    $pattern = '/"[^"\\\\]*+(?:\\\\.[^"\\\\]*+)*+"/s';
    $filtered = preg_replace_callback($pattern, function (array $matches) use ($depth, $flags, $escape, &$failed): string {
      $value = json_decode($matches[0]);
      if (!is_string($value)) {
        $failed = TRUE;
        return $matches[0];
      }
      // A string can itself be a serialized JSON document (tool arguments
      // commonly are); preserve its structure too.
      $new_value = $this->filterJsonText($value, $depth + 1);
      if ($new_value === NULL) {
        $modified = FALSE;
        $new_value = $this->filterScalarString($value, $modified);
      }
      if ($new_value === $value && !$escape) {
        return $matches[0];
      }
      $encoded = json_encode($new_value, $flags);
      if ($encoded === FALSE) {
        $failed = TRUE;
        return $matches[0];
      }
      return $encoded;
    }, $json);
    return $failed || $filtered === NULL ? NULL : $filtered;
  }

  /**
   * Filter a single fenced code block containing a JSON payload.
   *
   * The fence is ``` or ~~~, the language tag is "json" or starts with it
   * (JSON, jsonc), and a reply that was cut off may lack the closing fence.
   *
   * @param string $text
   *   The text that may contain a fenced JSON payload.
   * @param int $depth
   *   Current recursion depth for nested JSON-in-string values.
   *
   * @return string|null
   *   The filtered text, or NULL when no fenced JSON payload was found.
   */
  protected function filterFencedJsonText(string $text, int $depth): ?string {
    $fence_pattern = '/^(?<prefix>.*?)(?<open>(?<fence>```|~~~)[ \t]*(?<lang>json[a-z0-9]*)?[ \t]*\R)(?<payload>.*?)(?:(?<close>\R[ \t]*\k<fence>)(?<suffix>.*)|(?<tail>\s*))$/si';
    if (!preg_match($fence_pattern, $text, $fence)) {
      return NULL;
    }
    // Without a closing fence only trailing whitespace follows the payload.
    $fence += ['tail' => ''];

    // A bare fence is only trusted as a JSON container when nothing but
    // whitespace surrounds it; with prose around it the language tag is
    // required. Multiple fences fall through to the regular pipeline.
    if ($fence['lang'] === '' && (trim($fence['prefix']) !== '' || trim($fence['suffix']) !== '')) {
      return NULL;
    }
    if (str_contains($fence['suffix'], '```') || str_contains($fence['suffix'], '~~~')) {
      return NULL;
    }

    $payload = trim($fence['payload']);
    if ($payload === '' || ($payload[0] !== '{' && $payload[0] !== '[')) {
      return NULL;
    }
    $decoded = json_decode($payload);
    if (!is_array($decoded) && !is_object($decoded)) {
      return NULL;
    }

    $encoded = $this->filterJsonStrings($fence['payload'], $depth, FALSE);
    if ($encoded === NULL) {
      return NULL;
    }
    $prose_modified = FALSE;
    $result = $this->filterScalarString($fence['prefix'], $prose_modified)
      . $fence['open'] . $encoded . $fence['close']
      . $this->filterScalarString($fence['suffix'], $prose_modified)
      . $fence['tail'];

    // The prose was filtered in two pieces, so a tag opened before the fence
    // and closed after it was never seen whole. If the assembled text still
    // holds anything unsafe, let the regular pipeline handle all of it.
    return $this->hasUnsafeMarkup($result) ? NULL : $result;
  }

  /**
   * Filter a JSON payload that could not be decoded.
   *
   * Used for malformed or truncated payloads and partial streamed chunks.
   * Disallowed URLs are removed as plain text, which keeps the structure
   * intact; dangerous markup then goes through the same DOM walk as JSON
   * string values, which only rewrites the text when it finds something.
   *
   * @param string $text
   *   The JSON-like text.
   *
   * @return string
   *   The filtered text.
   */
  protected function filterJsonFragment(string $text): string {
    // Resolve disallowed Markdown links as a whole first, so the URL pattern
    // does not leave broken link syntax like "[text]()".
    $text = $this->filterUrlsInPlainText($this->filterMarkdownLinks($text, FALSE));
    $modified = FALSE;
    return $this->filterScalarString($text, $modified);
  }

  /**
   * Check whether text, read as markup, holds something the filter removes.
   *
   * @param string $text
   *   The text to check.
   *
   * @return bool
   *   TRUE when the Markdown filter or the DOM walk would change the text.
   */
  protected function hasUnsafeMarkup(string $text): bool {
    return $this->filterMarkdownLinks($text) !== $text
      || (str_contains($text, '<') && $this->filterHtmlLinks($this->html5->loadHTMLFragment($text)));
  }

  /**
   * Filter a single plain string through the Markdown and HTML pipelines.
   *
   * Unlike ::filterText(), the input is only rewritten when a disallowed
   * URL or dangerous construct was actually found: the HTML5 serializer
   * output is used only when the DOM walk reported a modification, so
   * benign strings (including ones with stray "<" or "&") pass through
   * byte-for-byte instead of being entity-encoded by the round trip.
   *
   * @param string $value
   *   The string to filter.
   * @param bool $modified
   *   Set to TRUE when filtering changed the string, FALSE otherwise.
   *
   * @return string
   *   The filtered string, or the original string when nothing was found.
   */
  protected function filterScalarString(string $value, bool &$modified): string {
    $modified = FALSE;
    $filtered = $this->filterMarkdownLinks($value);
    // The Markdown callbacks return the match untouched when the URL is
    // allowed, so a difference here always means a violation was handled.
    $md_modified = ($filtered !== $value);

    // Without "<" the HTML5 fragment contains no elements, so the DOM
    // filter cannot find anything; skip the expensive parse.
    $html_modified = FALSE;
    if (str_contains($filtered, '<')) {
      $dom = $this->html5->loadHTMLFragment($filtered);
      $html_modified = $this->filterHtmlLinks($dom);
      if ($html_modified) {
        $filtered = $this->html5->saveHTML($dom);
      }
    }

    $modified = $md_modified || $html_modified;
    return $modified ? $filtered : $value;
  }

  /**
   * Filter Markdown links and images.
   *
   * @param string $markdown
   *   The Markdown content.
   * @param bool $allow_rewrite
   *   (optional) FALSE to always reduce a disallowed link to its text, even if
   *   rewriting disallowed links is enabled. Defaults to TRUE.
   * @param bool $preserve_mailto
   *   (optional) TRUE to preserve mailto URLs, matching plain-text mode.
   *
   * @return string
   *   The filtered Markdown.
   */
  protected function filterMarkdownLinks(string $markdown, bool $allow_rewrite = TRUE, bool $preserve_mailto = FALSE): string {
    $markdown = $this->filterMarkdownInlineLinks($markdown, $allow_rewrite, $preserve_mailto);

    // Markdown auto links (<scheme:...>) and reference-style link definitions
    // ([label]: url) are not covered by the inline patterns above, yet a
    // consumer's Markdown renderer compiles both forms to real links. Their
    // URLs must be checked against the allowlist too, otherwise disallowed
    // schemes and hosts can be smuggled through these syntaxes.
    $autolinkPattern = '/<([a-z][a-z0-9+.-]*:[^\s<>]*)>/i';
    $markdown = preg_replace_callback($autolinkPattern, function ($matches) use ($preserve_mailto) {
      if (!($preserve_mailto && $this->isMailtoUrl($matches[1])) && !$this->isUrlAllowed($matches[1])) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed Markdown autolink with URL: @url',
          ['@url' => $matches[1]]
        );
        return '';
      }
      return $matches[0];
    }, $markdown);

    // The label may contain backslash-escaped brackets ("[a\]b]: url"), and
    // the definition may sit inside a block quote, a list item or any
    // indentation a container allows.
    $referenceDefinitionPattern = '/^[ \t]*(?:(?:>|[-+*]|\d{1,9}[.)])[ \t]*)*\[(?:\\\\.|[^\\\\\]])+\]:\s*(\S+).*$/m';
    $markdown = preg_replace_callback($referenceDefinitionPattern, function ($matches) use ($preserve_mailto) {
      // CommonMark allows the destination to be wrapped in angle brackets
      // ("[ref]: <url>"). Strip them before the host check, otherwise
      // parse_url() cannot detect the host behind the leading "<" and the
      // disallowed URL is let through.
      $url = trim($matches[1], '<>');
      if (!$this->isMarkdownUrlAllowed($url, $preserve_mailto)) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed Markdown reference link with URL: @url',
          ['@url' => $url]
        );
        return '';
      }
      return $matches[0];
    }, $markdown);

    return $markdown;
  }

  /**
   * Filter inline Markdown links and images: [text](url) and ![alt](url).
   *
   * This is a bracket-aware scanner rather than a regex. Renderers such as
   * league/commonmark and showdown accept link text with nested or escaped
   * brackets ("[a[b]](url)", "[a\]](url)"), destinations in angle brackets or
   * with balanced parentheses, and titles in any quote style. A pattern that
   * misses any of these never checks the URL, so the disallowed link survives
   * and is rendered by the consumer. The scanner errs on the lenient side
   * (e.g. it allows whitespace between "]" and "(" like showdown does) so it
   * sees at least everything a renderer would turn into a link.
   *
   * @param string $markdown
   *   The Markdown content.
   * @param bool $allow_rewrite
   *   (optional) FALSE to always reduce a disallowed link to its text, even if
   *   rewriting disallowed links is enabled. Defaults to TRUE.
   * @param bool $preserve_mailto
   *   (optional) TRUE to preserve mailto URLs, matching plain-text mode.
   *
   * @return string
   *   The filtered Markdown.
   */
  protected function filterMarkdownInlineLinks(string $markdown, bool $allow_rewrite = TRUE, bool $preserve_mailto = FALSE): string {
    $links = [];
    $openers = [];
    $escaped = [];
    $length = strlen($markdown);
    for ($i = 0; $i < $length; $i++) {
      $char = $markdown[$i];
      if ($char === '\\') {
        $escaped[$i + 1] = TRUE;
        $i++;
        continue;
      }
      if ($char === '[') {
        $openers[] = $i;
        continue;
      }
      if ($char !== ']') {
        continue;
      }
      $destination = $this->parseMarkdownLinkDestination($markdown, $i + 1);
      if ($openers === []) {
        // A code span, inline HTML or autolink can hide the opening "[" or
        // consume an earlier "]", so a renderer may still link this "](url)".
        // Check the destination anyway and drop "](url)" if not allowed.
        if ($destination !== NULL) {
          $links[] = [
            'start' => $i,
            'text_start' => $i,
            'text_end' => $i,
            'end' => $destination['end'],
            'url' => $destination['url'],
            'image' => FALSE,
          ];
          $i = $destination['end'] - 1;
        }
        continue;
      }
      $open = array_pop($openers);
      if ($destination === NULL) {
        continue;
      }
      $isImage = $open > 0 && $markdown[$open - 1] === '!' && empty($escaped[$open - 1]);
      $links[] = [
        'start' => $isImage ? $open - 1 : $open,
        'text_start' => $open + 1,
        'text_end' => $i,
        'end' => $destination['end'],
        'url' => $destination['url'],
        'image' => $isImage,
      ];
      // Brackets inside the destination are not link syntax.
      $i = $destination['end'] - 1;
    }

    if ($links === []) {
      return $markdown;
    }
    usort($links, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);
    return $this->rebuildMarkdownLinks($markdown, $links, 0, $length, $allow_rewrite, $preserve_mailto);
  }

  /**
   * Parse an inline link destination following the closing "]".
   *
   * @param string $markdown
   *   The Markdown content.
   * @param int $offset
   *   The offset just after the closing "]" of the link text.
   *
   * @return array|null
   *   An array with the raw 'url' and the 'end' offset of the link, or NULL if
   *   no "(" follows the link text.
   */
  protected function parseMarkdownLinkDestination(string $markdown, int $offset): ?array {
    if (!preg_match('/\G[ \t]*\(\s*/', $markdown, $matches, 0, $offset)) {
      return NULL;
    }
    $length = strlen($markdown);
    $position = $offset + strlen($matches[0]);
    $url = NULL;

    // Destination wrapped in angle brackets: "(<url>)".
    if ($position < $length && $markdown[$position] === '<') {
      $close = strcspn($markdown, ">\n", $position + 1) + $position + 1;
      if ($close < $length && $markdown[$close] === '>') {
        $url = substr($markdown, $position + 1, $close - $position - 1);
        $position = $close + 1;
      }
    }

    // Bare destination: ends at whitespace or an unbalanced ")".
    if ($url === NULL) {
      $start = $position;
      $depth = 0;
      while ($position < $length) {
        $char = $markdown[$position];
        if ($char === '\\') {
          $position += 2;
          continue;
        }
        if (ord($char) <= 0x20) {
          break;
        }
        if ($char === '(') {
          $depth++;
        }
        elseif ($char === ')') {
          if ($depth === 0) {
            break;
          }
          $depth--;
        }
        $position++;
      }
      $position = min($position, $length);
      $url = substr($markdown, $start, $position - $start);
    }

    // Optional title in double quotes, single quotes or parentheses, then ")".
    $tail = '/\G\s*(?:"(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|\((?:\\\\.|[^()\\\\])*\))?\s*\)/';
    if (preg_match($tail, $markdown, $matches, 0, $position)) {
      return ['url' => $url, 'end' => $position + strlen($matches[0])];
    }

    // Not a well-formed link, but a lenient renderer may still link it, so
    // fail closed and still check the destination. Removing up to the end of
    // the destination is enough to break the link syntax.
    return ['url' => $url, 'end' => $position];
  }

  /**
   * Rebuild a span of Markdown, filtering the inline links found inside it.
   *
   * @param string $markdown
   *   The Markdown content.
   * @param array $links
   *   The links found by ::filterMarkdownInlineLinks(), sorted by start. Links
   *   are either disjoint or nested inside another link's text.
   * @param int $from
   *   The start offset of the span.
   * @param int $to
   *   The end offset of the span.
   * @param bool $allow_rewrite
   *   (optional) FALSE to always reduce a disallowed link to its text, even if
   *   rewriting disallowed links is enabled. Defaults to TRUE.
   * @param bool $preserve_mailto
   *   (optional) TRUE to preserve mailto URLs, matching plain-text mode.
   *
   * @return string
   *   The filtered span.
   */
  protected function rebuildMarkdownLinks(string $markdown, array $links, int $from, int $to, bool $allow_rewrite = TRUE, bool $preserve_mailto = FALSE): string {
    $output = '';
    $cursor = $from;
    foreach ($links as $link) {
      // Skip links outside this span or nested in a link already handled.
      if ($link['start'] < $cursor || $link['end'] > $to) {
        continue;
      }
      $output .= substr($markdown, $cursor, $link['start'] - $cursor);
      $cursor = $link['end'];
      $text = $this->rebuildMarkdownLinks($markdown, $links, $link['text_start'], $link['text_end'], $allow_rewrite, $preserve_mailto);
      $url = $link['url'];

      if ($url === '' || $this->isMarkdownUrlAllowed($url, $preserve_mailto)) {
        $output .= substr($markdown, $link['start'], $link['text_start'] - $link['start'])
          . $text
          . substr($markdown, $link['text_end'], $link['end'] - $link['text_end']);
        continue;
      }

      if ($link['image']) {
        $this->loggerFactory->get('ai')->warning(
          'Removed disallowed Markdown image with URL: @url',
          ['@url' => $url]
        );
        continue;
      }

      $this->loggerFactory->get('ai')->warning(
        'Removed disallowed Markdown link with URL: @url',
        ['@url' => $url]
      );
      // Rewriting keeps the link but shows its target, which only makes sense
      // for web URLs. Hostless schemes such as javascript: are always removed.
      if ($allow_rewrite && $this->shouldRewriteLinks() && preg_match('#^\s*(?:https?:)?//[^\s()<>]+$#i', $url)) {
        $output .= '[' . $url . '](' . $url . ')';
      }
      else {
        $output .= $text;
      }
    }
    return $output . substr($markdown, $cursor, $to - $cursor);
  }

  /**
   * Check a Markdown link destination against the allowlist.
   *
   * Renderers resolve backslash escapes ("javascript\:") and some strip a
   * stray "<" or ">" around the destination, so every reading of the
   * destination must be allowed.
   *
   * @param string $url
   *   The raw destination as written in the Markdown.
   * @param bool $preserve_mailto
   *   (optional) TRUE to allow mailto URLs, matching plain-text mode.
   *
   * @return bool
   *   TRUE if allowed, FALSE otherwise.
   */
  protected function isMarkdownUrlAllowed(string $url, bool $preserve_mailto = FALSE): bool {
    // Plain-text mode preserves mailto URLs. Applying the Markdown pass must
    // preserve that policy for links and autolinks as well as bare URLs.
    if ($preserve_mailto && $this->isMailtoUrl($url)) {
      return TRUE;
    }
    $unescaped = (string) preg_replace('/\\\\([!-\/:-@\[-`{-~])/', '$1', $url);
    foreach ([$url, $unescaped] as $candidate) {
      if (!$this->isUrlAllowed($candidate) || !$this->isUrlAllowed(trim($candidate, '<>'))) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Checks whether a URL uses the mailto scheme.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE for mailto URLs, FALSE otherwise.
   */
  protected function isMailtoUrl(string $url): bool {
    return preg_match('/^mailto:/i', $url) === 1;
  }

  /**
   * Check if a URL is allowed.
   *
   * @param string $url
   *   The URL to check.
   *
   * @return bool
   *   TRUE if allowed, FALSE otherwise.
   */
  protected function isUrlAllowed(string $url): bool {
    // Decode HTML character references first, so a scheme obfuscated with
    // numeric or named entities (e.g. "java&#9;script:", "java&#x9;script:" or
    // "java&Tab;script:") is resolved to its real form before the scheme check.
    // Markdown/HTML renderers decode these, so without this step the entity
    // breaks the scheme regex, parse_url() finds no host, and the dangerous URL
    // is let through. Mirrors the decoding done in ::filterUrlsInPlainText().
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5);

    // Normalize the URL the way browsers do before they detect the scheme:
    // ASCII tab, line feed and carriage return are removed from anywhere in
    // the URL (per the WHATWG URL spec), and leading/trailing C0 control
    // characters and spaces are trimmed. This prevents obfuscating a dangerous
    // scheme with whitespace or control characters, e.g. "java<TAB>script:".
    $trimmedUrl = trim((string) preg_replace('/[\x09\x0A\x0D]/', '', $url), "\x00..\x20");

    // Browsers treat a backslash as a forward slash in http(s) URLs, so
    // "/\evil.com" or "\\evil.com" resolve to "https://evil.com". Normalize
    // them so these are checked as protocol-relative URLs, not relative paths.
    $trimmedUrl = str_replace('\\', '/', $trimmedUrl);

    // Deny empty URLs.
    if ($trimmedUrl === '') {
      return FALSE;
    }

    // Browsers ignore the number of slashes after an http(s) scheme, so
    // "https:\/\/host" (JSON-escaped) or "https:////host" resolve to
    // "https://host". Collapse them so the real host is checked.
    $trimmedUrl = (string) preg_replace('#^(https?:)/+(?=[^/])#i', '$1//', $trimmedUrl);
    // The same applies to a run of three or more leading slashes or
    // backslashes ("\/\/host"), which browsers read as "//host".
    $trimmedUrl = (string) preg_replace('#^/{3,}(?=[^/])#', '//', $trimmedUrl);

    // Allow anchor links.
    if (str_starts_with($trimmedUrl, '#')) {
      return TRUE;
    }

    // Handle protocol-relative URLs (e.g., //www.yahoo.com).
    if (str_starts_with($trimmedUrl, '//')) {
      $parsedUrl = parse_url($trimmedUrl);
      if (!empty($parsedUrl['host'])) {
        return $this->isHostAllowed($parsedUrl['host']);
      }
      return FALSE;
    }

    // Allow relative paths.
    if (str_starts_with($trimmedUrl, '/')) {
      return TRUE;
    }

    // Only allow http://, https://, and public:// schemes.
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $trimmedUrl)) {
      $scheme = strtolower(parse_url($trimmedUrl, PHP_URL_SCHEME) ?? '');
      if ($scheme === 'public') {
        return TRUE;
      }
      if (!in_array($scheme, ['http', 'https'], TRUE)) {
        return FALSE;
      }
    }
    // Block scheme-like patterns without double slash (javascript:, data:).
    elseif (preg_match('#^[a-z][a-z0-9+.-]*:#i', $trimmedUrl)) {
      return FALSE;
    }

    // Parse the URL.
    $parsedUrl = parse_url($trimmedUrl);

    // If no host is present, it's not a recognizable URL (e.g. bare text
    // like "byte.test" or "www.example.com"). Allow it through — only
    // explicit protocols (http://, https://) and protocol-relative URLs
    // (//) should be filtered.
    if (empty($parsedUrl['host'])) {
      return TRUE;
    }

    return $this->isHostAllowed($parsedUrl['host']);
  }

  /**
   * Check if a hostname is in the allowed domains list.
   *
   * @param string $hostname
   *   The hostname to check.
   *
   * @return bool
   *   TRUE if allowed, FALSE otherwise.
   */
  protected function isHostAllowed(string $hostname): bool {
    $allowedDomains = $this->getAllowedDomains();

    if (empty($allowedDomains)) {
      return FALSE;
    }

    foreach ($allowedDomains as $allowedDomain) {
      if ($this->matchesWildcardDomain($hostname, $allowedDomain)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check if a multi-URL attribute value has only allowed URLs.
   *
   * Handles srcset (comma-separated with optional descriptors) and
   * ping (space-separated URLs).
   *
   * @param string $value
   *   The attribute value.
   *
   * @return bool
   *   TRUE if all URLs are allowed, FALSE otherwise.
   */
  protected function isMultiUrlAttributeAllowed(string $value): bool {
    $entries = preg_split('/\s*,\s*/', trim($value));
    foreach ($entries as $entry) {
      $parts = preg_split('/\s+/', trim($entry), 2);
      $url = $parts[0] ?? '';
      if ($url !== '' && !$this->isUrlAllowed($url)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Check if a hostname matches a wildcard domain pattern.
   *
   * @param string $hostname
   *   The hostname to check.
   * @param string $pattern
   *   The domain pattern (can include * wildcard for subdomains).
   *
   * @return bool
   *   TRUE if matches, FALSE otherwise.
   */
  protected function matchesWildcardDomain(string $hostname, string $pattern): bool {
    // Normalize both to lowercase for case-insensitive comparison.
    $hostname = strtolower($hostname);
    $pattern = strtolower($pattern);

    // Exact match.
    if ($hostname === $pattern) {
      return TRUE;
    }

    // Check for wildcard pattern.
    if (strpos($pattern, '*') !== FALSE) {
      // Convert wildcard pattern to regex.
      // *.example.com should match test.example.com and stage.test.example.com
      // but NOT example.com itself.
      // First escape all special regex characters.
      $regexPattern = preg_quote($pattern, '/');
      // Then replace the escaped wildcard with the subdomain regex.
      $regexPattern = str_replace('\\*\\.', '([a-zA-Z0-9-]+\.)+', $regexPattern);
      $regexPattern = '/^' . $regexPattern . '$/i';

      return preg_match($regexPattern, $hostname) === 1;
    }

    return FALSE;
  }

}
