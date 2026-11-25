# HostnameFilter Service

The `HostnameFilter` service filters AI-generated output to remove or rewrite links and images with disallowed hostnames.

## Configuration

By default no hostnames are allowed, meaning all links and images will be stripped from AI-generated content. You can configure allowed hostnames and rewrite behavior in the Drupal admin UI at **Configuration » AI » Default Settings** (/admin/config/ai/settings).

### Allowed Hostnames
You can specify a list of allowed hostnames (one per line). Wildcards are supported using `*` to match any subdomain. For example:

```
example.com
*.cdn.example.com
trustedsite.org
```

### Rewrite Links
You can choose whether to rewrite links to allowed hostnames or remove them entirely. If enabled, links to allowed hostnames will have their inner HTML rewritten to match the actual URL, ensuring transparency about where the link points.

### Full Trust Mode
If you really want to allow all hostnames to be trusted (not recommended), you can add the following to your `settings.php` file:

```php
// AI output filtering configuration.
$settings['ai_output'] = [
  // Full trust mode - bypass all filtering.
  // Default: FALSE
  'full_trust_mode' => TRUE,
];
```

*Note that these can be overwritten per AI request on the ChatInput object, if needed.*

## Examples

### Default Behavior (rewrite_links = FALSE)

**HTML Input:**
```html
<a href="https://evil.com/phishing">Click here for free gift</a>
```

**Output:**
```html
<a>Click here for free gift</a>
```

**Markdown Input:**
```markdown
[Click here for free gift](https://evil.com/phishing)
```

**Output:**
```markdown
Click here for free gift
```

### Rewrite Mode (rewrite_links = TRUE)


**HTML Input:**
```html
<a href="https://evil.com/phishing?data=exfiltration">Click here for free gift</a>
```

**Output:**
```html
<a href="https://evil.com/phishing?data=exfiltration">https://evil.com/phishing?data=exfiltration</a>
```

**Markdown Input:**
```markdown
[Click here for free gift](https://evil.com/phishing?data=exfiltration)
```

**Output:**
```markdown
[https://evil.com/phishing?data=exfiltration](https://evil.com/phishing?data=exfiltration)
```

## Why Use Rewrite Mode?

Rewrite mode is useful for transparency or when you trust your end-users to read the actual link text - it ensures users can see exactly where a link points before clicking, preventing deceptive link text from hiding malicious URLs.

## Allowed Content

The following are always allowed regardless of domain settings:
- Relative URLs: `/path/to/page`
- Absolute paths: `/absolute/path`
- Anchor links: `#section`
- Mailto links: `mailto:user@example.com`
- Protocol-relative URLs: `//example.com/page`

## JSON and Structured Output

JSON payloads are filtered string by string, so the result still decodes. A payload is detected when the text is wholly a JSON object or array, or holds a single fenced block with one. The fence can be ```` ``` ```` or `~~~`, the language tag is `json` in any case (`jsonc` works too), and the closing fence can be missing when a reply was cut off.

- A clean payload is returned unchanged.
- When something is removed, only the strings that changed are rewritten. Numbers and formatting stay as they came in.
- JSON that is mixed into prose without a fence, or that sits in single backticks, is not detected and is filtered as prose.

Structured output responses (`ChatInput::setChatStructuredJsonSchema()`), streamed ones included, also enable JSON mode. In JSON mode, text that does not decode (malformed, truncated or a partial chunk) still gets URL and HTML filtering, but is only rewritten where something unsafe is found. A streamed chunk cannot be decoded on its own, so a chunk that loses a script, an event handler or another unsafe tag may no longer be valid JSON. To opt out for a request:

```php
$input->setHostnameFilter(new HostnameFilterDto(jsonMode: FALSE));
```

## Images

Images with disallowed domains are always completely removed (the entire `<img>` tag or `![alt](url)` markdown). Rewrite mode only affects links, not images.

The reason for this is that images can be used to exfiltrate data or track users without their knowledge, just by being loaded in the browser. Therefore, it's safer to remove them entirely if they come from untrusted sources.

## Usage in Code

```php
// Inject the service.
$hostnameFilter = \Drupal::service('ai.hostname_filter_service');

// Filter AI-generated content.
$filteredOutput = $hostnameFilter->filterText($aiGeneratedContent);
```

## Programmatic Configuration (for testing)

```php
// Set allowed domains programmatically.
$hostnameFilter->setAllowedDomains(['example.com', '*.cdn.example.com']);

// Enable/disable rewrite mode.
$hostnameFilter->setRewriteLinks(TRUE);

// Filter content.
$result = $hostnameFilter->filterText($content);
```

## Markdown consumers

By default, HTML tags are filtered even when surrounded by backticks or indented
by four spaces. Those characters do not prevent an HTML consumer from loading an
image or following a link.

If your consumer renders the output as Markdown, explicitly enable Markdown mode
to preserve HTML examples inside Markdown code spans and blocks:

```php
use Drupal\ai\Dto\HostnameFilterDto;

$chatInput->setHostnameFilter(new HostnameFilterDto(markdownMode: TRUE));
```

For direct service calls, use `$hostnameFilter->setMarkdownMode(TRUE)`. Restore
its previous settings afterwards with `snapshotSettings()` and `restoreSettings()`
because the service is shared. Provider calls restore the per-request override
automatically.

Only enable this mode when the consumer renders Markdown. Actual HTML outside
Markdown code still passes through the HTML filter; mixed Markdown and HTML can
therefore still be HTML-encoded. Plain-text mode takes precedence over Markdown
mode.
