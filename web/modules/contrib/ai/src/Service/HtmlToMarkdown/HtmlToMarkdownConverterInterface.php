<?php

declare(strict_types=1);

namespace Drupal\ai\Service\HtmlToMarkdown;

/**
 * Interface for services converting HTML to Markdown.
 */
interface HtmlToMarkdownConverterInterface {

  /**
   * Converts a given HTML string into Markdown format.
   *
   * @param string $html
   *   The HTML content to convert.
   *
   * @return string
   *   The resulting Markdown string, or an empty string if the input is
   *   empty.
   *
   * @throws \Drupal\ai\Exception\HtmlToMarkdownConverterException
   *   Thrown when the conversion fails.
   */
  public function convert(string $html): string;

}
