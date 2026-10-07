## HTML to Markdown Converter

AI Core module contains service `ai.html_to_markdown_converter` for HTML→Markdown
implementation instead of each caller instantiating its own
`league/html-to-markdown` `HtmlConverter`, and makes that implementation
swappable in favor of the optional `drupal/markdownify` module if it is installed.

**New service & interface**

- `Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverterInterface` — single `convert(string $html): string` method.
- `Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter` (default impl) — wraps `league/html-to-markdown`, options
   read from new `ai.html_to_markdown.settings` config, plus an AI-module-only `strip_whitespace` post-processing step
   (trims trailing whitespace, collapses blank lines).
-  Settings page is here `/admin/config/ai/html-to-markdown`, linked from Configuration. It is possible to select how
   HTML is converted: what heading types to use, lists, etc.

**Optional markdownify integration**

When [Markdownify](https://www.drupal.org/project/markdownify) module is installed, its service takes over.
Markdownify provides league plugin by default and it is also possible to add other plugins for HTML to
Markdown conversion. Configuration and plugin selection is done on the following settings page: `/admin/config/services/markdownify`

