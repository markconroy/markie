<?php

namespace Drupal\ai\Hook;

use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter;
use Drupal\ai\Service\HtmlToMarkdown\StripExcessWhitespaceTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Contains hook related to HTML to Markdown conversion.
 */
class HtmlToMarkdown {

  use StripExcessWhitespaceTrait;

  /**
   * The converter settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  public function __construct(
    ConfigFactoryInterface $config_factory,
  ) {
    $this->config = $config_factory->get(HtmlToMarkdownConverter::CONFIG_NAME);
  }

  /**
   * Implements hook_markdownify_entity_markdown_alter().
   */
  #[Hook('markdownify_entity_markdown_alter')]
  public function markdownifyEntityMarkdownAlter(&$markdown, $context) {
    if ($this->config->get('strip_whitespace') ?? TRUE) {
      $markdown = $this->stripExcessWhitespace($markdown);
    }
  }

}
