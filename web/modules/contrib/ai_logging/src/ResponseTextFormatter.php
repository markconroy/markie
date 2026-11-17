<?php

declare(strict_types=1);

namespace Drupal\ai_logging;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_logging\Thread\Transcript;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Formats the reply text of an AI log for display.
 *
 * A structured (JSON schema) call stores its JSON answer as its reply text,
 * so there is no separate field for it. When the text decodes to a JSON
 * object or array it is pretty-printed and labeled "Result", otherwise it is
 * shown as it is and labeled "Reply".
 */
class ResponseTextFormatter {

  use StringTranslationTrait;

  /**
   * The HTML tags rendered Markdown may keep. Images are left out.
   */
  protected const MARKDOWN_TAGS = [
    'a', 'blockquote', 'br', 'code', 'del', 'em', 'h1', 'h2', 'h3', 'h4',
    'h5', 'h6', 'hr', 'li', 'ol', 'p', 'pre', 'strong', 'table', 'tbody',
    'td', 'th', 'thead', 'tr', 'ul',
  ];

  /**
   * The Markdown converter, created when first needed.
   */
  protected ?MarkdownConverter $markdownConverter = NULL;

  /**
   * Formats reply text.
   *
   * @param string $text
   *   The reply text, as stored in the response_text field.
   *
   * @return array{label: \Drupal\Core\StringTranslation\TranslatableMarkup, text: string, structured: bool}
   *   The label to show, the text to show and whether the text is a
   *   structured result.
   */
  public function format(string $text): array {
    $decoded = Json::decode($text);
    if (is_array($decoded)) {
      // A turn that only called tools, as recorded by
      // LogPostRequestEventSubscriber::getToolCallsText(). It is neither a
      // reply nor a structured result, and labelling it either way reads as
      // though the model answered something.
      if (isset($decoded['tool_calls'])) {
        return [
          'label' => $this->t('Tool calls'),
          'text' => (string) json_encode($decoded['tool_calls'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
          'structured' => TRUE,
        ];
      }
      return [
        'label' => $this->t('Result'),
        'text' => (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'structured' => TRUE,
      ];
    }
    return [
      'label' => $this->t('Reply'),
      'text' => $text,
      'structured' => FALSE,
    ];
  }

  /**
   * Summarizes reply text on one line, for a thread's overview.
   *
   * @param string $text
   *   The reply text, as stored in the response_text field.
   * @param int $length
   *   The longest summary of a reply or result, in characters.
   *
   * @return string
   *   For tool calls, the tools called ("search, read ×2"); otherwise the
   *   start of the reply or result, on one line.
   */
  public function summarize(string $text, int $length = 100): string {
    $calls = $this->getToolCalls($text);
    if ($calls !== NULL) {
      $counts = [];
      foreach ($calls as $call) {
        $counts[$call['name']] = ($counts[$call['name']] ?? 0) + 1;
      }
      $names = [];
      foreach ($counts as $name => $count) {
        $names[] = $count > 1 ? $name . ' ×' . $count : $name;
      }
      return (string) $this->t('Tool calls: @names', ['@names' => implode(', ', $names)]);
    }
    $line = trim((string) preg_replace('/\s+/u', ' ', $text));
    return mb_strlen($line) > $length ? rtrim(mb_substr($line, 0, $length)) . '…' : $line;
  }

  /**
   * Gets the tool calls from reply text, if it records a tool-only turn.
   *
   * @param string $text
   *   The reply text, as stored in the response_text field.
   *
   * @return array<int, array{name: string, arguments: array}>|null
   *   Each call's tool name and arguments, or NULL if the text isn't a
   *   record of tool calls.
   *
   * @see \Drupal\ai_logging\EventSubscriber\LogPostRequestEventSubscriber::getToolCallsText()
   */
  public function getToolCalls(string $text): ?array {
    $decoded = Json::decode($text);
    if (!is_array($decoded) || !isset($decoded['tool_calls']) || !is_array($decoded['tool_calls'])) {
      return NULL;
    }
    $calls = [];
    foreach ($decoded['tool_calls'] as $call) {
      $calls[] = [
        'name' => is_array($call) ? (string) ($call['name'] ?? '?') : '?',
        'arguments' => is_array($call) && is_array($call['arguments'] ?? NULL) ? $call['arguments'] : [],
      ];
    }
    return $calls;
  }

  /**
   * Summarizes a tool call's arguments on one line.
   *
   * @param array $arguments
   *   The arguments, keyed by name.
   * @param int $length
   *   The longest summary, in characters.
   *
   * @return string
   *   For example 'query: "Myanmar", limit: 5'.
   */
  public function summarizeArguments(array $arguments, int $length = 80): string {
    $parts = [];
    foreach ($arguments as $name => $value) {
      $parts[] = $name . ': ' . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    return $this->summarize(implode(', ', $parts), $length);
  }

  /**
   * Checks whether text looks like Markdown, e.g. an assistant's answer.
   *
   * @param string $text
   *   The text.
   *
   * @return bool
   *   TRUE if it has headings, lists, emphasis, links, code fences,
   *   quotes or tables written in Markdown.
   */
  public function isMarkdown(string $text): bool {
    $patterns = [
      // Headings, lists, quotes, fences and table rows, at a line's start.
      '/^ {0,3}(#{1,6} |[-*+] |\d+[.)] |> |```|~~~|\|.*\|\s*$)/m',
      // Bold text, and links.
      '/\*\*[^*\n]+\*\*|__[^_\n]+__|\[[^\]\n]+\]\([^)\s]+\)/',
    ];
    foreach ($patterns as $pattern) {
      if (preg_match($pattern, $text) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Renders Markdown as HTML.
   *
   * The text comes from a model, which a prompt injection can steer, so it
   * is treated as untrusted:
   * - Any HTML in it is escaped rather than passed through.
   * - Links with unsafe protocols are dropped, and the rest are marked
   *   noopener, noreferrer and nofollow, so following one doesn't tell the
   *   other site which log was being read.
   * - Images are dropped, since loading one would send a request, and
   *   anything the model put in its URL, as soon as the page is viewed.
   *
   * @param string $text
   *   The Markdown.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The HTML.
   */
  public function renderMarkdown(string $text): MarkupInterface {
    $this->markdownConverter ??= $this->createMarkdownConverter();
    $html = (string) $this->markdownConverter->convert($text);
    return Markup::create(Xss::filter($html, static::MARKDOWN_TAGS));
  }

  /**
   * Creates the converter that renders Markdown.
   *
   * @return \League\CommonMark\MarkdownConverter
   *   The converter, for GitHub-flavored Markdown.
   */
  protected function createMarkdownConverter(): MarkdownConverter {
    $environment = new Environment([
      'html_input' => 'escape',
      'allow_unsafe_links' => FALSE,
      'external_link' => [
        'internal_hosts' => [],
        'nofollow' => 'external',
        'noopener' => 'external',
        'noreferrer' => 'external',
      ],
    ]);
    $environment->addExtension(new CommonMarkCoreExtension());
    $environment->addExtension(new GithubFlavoredMarkdownExtension());
    $environment->addExtension(new ExternalLinkExtension());
    return new MarkdownConverter($environment);
  }

  /**
   * Builds a labeled, preformatted render array for a block of text.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string|\Drupal\Component\Render\MarkupInterface $text
   *   The text. A plain string is escaped when rendered; already-safe
   *   markup (see buildTranscript()) is rendered as it is.
   *
   * @return array
   *   A render array.
   */
  public function buildPreformatted(TranslatableMarkup $label, string|MarkupInterface $text): array {
    return [
      '#type' => 'inline_template',
      '#template' => '<div class="ai-logging-text"><strong class="ai-logging-text__label">{{ label }}</strong><pre class="ai-logging-text__value">{{ text }}</pre></div>',
      '#context' => [
        'label' => $label,
        'text' => $text,
      ],
    ];
  }

  /**
   * Builds the render array for reply text.
   *
   * @param string $text
   *   The reply text.
   *
   * @return array
   *   A render array.
   */
  public function build(string $text): array {
    $formatted = $this->format($text);
    return $this->buildPreformatted($formatted['label'], $formatted['text']);
  }

  /**
   * Builds a labeled, preformatted render array for a chat transcript.
   *
   * As buildPreformatted(), except that the lines marking where one message
   * ends and the next begins - the role, and any tool calls that role made -
   * are emphasized, so a long transcript can be skimmed for its structure
   * instead of read end to end.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $label
   *   The label.
   * @param string $text
   *   The transcript, escaped when rendered.
   *
   * @return array
   *   A render array.
   */
  public function buildTranscript(TranslatableMarkup $label, string $text): array {
    $lines = [];
    foreach (explode("\n", $text) as $line) {
      // Escaped before anything is added, so the only markup that ever
      // reaches the page is the emphasis added here - a transcript holds
      // whatever the user, the model and its tools said, none of it
      // trusted.
      $escaped = Html::escape($line);
      if (preg_match(Transcript::ROLE_PATTERN, $line, $matches) === 1) {
        // A role on its own line, optionally naming the tool whose result
        // follows, e.g. "user" or "tool (search_publications)". Capitalized
        // for reading only - the role itself stays as the providers write
        // it, and the tool's own name is left exactly as it is called.
        $escaped = '<strong>' . ucfirst($matches[1]) . Html::escape($matches[2] ?? '') . '</strong>';
      }
      elseif (preg_match('/^\[calls [^\]]+\]/', $line, $matches) === 1) {
        // Emphasize which tool was called, but not its arguments.
        $call = Html::escape($matches[0]);
        $escaped = '<strong>' . $call . '</strong>' . mb_substr($escaped, mb_strlen($call));
      }
      $lines[] = $escaped;
    }

    return $this->buildPreformatted($label, Markup::create(implode("\n", $lines)));
  }

}
