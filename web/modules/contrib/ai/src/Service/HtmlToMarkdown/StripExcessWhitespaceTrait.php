<?php

namespace Drupal\ai\Service\HtmlToMarkdown;

/**
 * Trait for classes that need to clean the string from excess whitespaces.
 */
trait StripExcessWhitespaceTrait {

  /**
   * Trims trailing whitespace and collapses runs of blank lines.
   *
   * @param string $markdown
   *   The string to clean.
   *
   * @return string
   *   The clean string.
   */
  public function stripExcessWhitespace(string $markdown): string {
    $markdown = preg_replace('/[ \t]+$/m', '', $markdown);
    $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);
    return trim($markdown);
  }

}
