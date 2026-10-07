## Markdown to HTML Converter

AI Core module contains service `ai.commonmark_converter` for Markdown→HTML
conversion, backed by `league/commonmark`, so that callers get a single,
injectable `CommonMarkConverter` instance instead of each caller instantiating
its own with its own set of options.

**New service & interface**

- `Drupal\ai\Service\CommonMarkConverterFactoryInterface` — single
  `fromOptions(array $options = []): CommonMarkConverter` method that builds a
  `League\CommonMark\CommonMarkConverter` from options merged onto the
  module's defaults.
- `Drupal\ai\Service\CommonMarkConverterFactory` (default impl) — merges
  caller-supplied options onto `CommonMarkConverterFactoryInterface::DEFAULT_OPTIONS`
  (`html_input` set to `strip`, `allow_unsafe_links` set to `false`) and
  returns a configured `CommonMarkConverter`.

**Services**

- `ai.commonmark_converter_factory` (aliased to
  `Drupal\ai\Service\CommonMarkConverterFactoryInterface`) — the factory
  described above. Inject this when custom converter options are needed, and
  call `fromOptions()` to build a converter.
- `ai.commonmark_converter` (aliased to `League\CommonMark\CommonMarkConverter`)
  — a ready-to-use `CommonMarkConverter` built from the factory's defaults, for
  the common case of converting Markdown to HTML without any custom options.

**Example usage**

```php
// Convert with the default converter.
$html = \Drupal::service('ai.commonmark_converter')->convert($markdown)->getContent();

// Or build a converter with custom options via the factory.
$converter = \Drupal::service('ai.commonmark_converter_factory')
  ->fromOptions(['html_input' => 'allow']);
$html = $converter->convert($markdown)->getContent();
```
