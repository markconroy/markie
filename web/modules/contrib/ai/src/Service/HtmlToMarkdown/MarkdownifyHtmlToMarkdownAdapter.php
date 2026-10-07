<?php

declare(strict_types=1);

namespace Drupal\ai\Service\HtmlToMarkdown;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\markdownify\MarkdownifyHtmlConverterInterface;

/**
 * Delegates HTML to Markdown conversion to the markdownify module.
 *
 * Only ever instantiated by \Drupal\ai\AiServiceProvider, and only when the
 * markdownify module is installed, so it is safe to reference markdownify's
 * classes here: they are guaranteed to be present and autoloadable.
 */
final class MarkdownifyHtmlToMarkdownAdapter implements HtmlToMarkdownConverterInterface {

  use StripExcessWhitespaceTrait;

  public function __construct(
    protected MarkdownifyHtmlConverterInterface $markdownifyConverter,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert(string $html): string {
    $markdown = $this->markdownifyConverter->convert($html);
    // strip_whitespace is an AI-module post-processing step, not a markdownify
    // option, so it is applied here for direct convert() callers just as the
    // default converter applies it. This keeps the setting effective for all
    // callers of ai.html_to_markdown_converter regardless of whether
    // markdownify is installed.
    if ($this->configFactory->get(HtmlToMarkdownConverter::CONFIG_NAME)->get('strip_whitespace') ?? TRUE) {
      $markdown = $this->stripExcessWhitespace($markdown);
    }
    return $markdown;
  }

}
