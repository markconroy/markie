<?php

declare(strict_types=1);

namespace Drupal\ai\Service\HtmlToMarkdown;

use Drupal\ai\Exception\HtmlToMarkdownConverterException;
use Drupal\Core\Config\ConfigFactoryInterface;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Default HTML to Markdown converter, based on league/html-to-markdown.
 *
 * Used whenever the markdownify module is not installed. When markdownify
 * is installed, \Drupal\ai\AiServiceProvider swaps this implementation out
 * for \Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter so
 * that markdownify's own plugins and settings are used instead.
 *
 * @see \Drupal\ai\Form\HtmlToMarkdownSettingsForm
 */
final class HtmlToMarkdownConverter implements HtmlToMarkdownConverterInterface {

  use StripExcessWhitespaceTrait;

  /**
   * Config settings key.
   */
  public const CONFIG_NAME = 'ai.html_to_markdown.settings';

  /**
   * The league/html-to-markdown option keys, in their config order.
   */
  public const OPTION_KEYS = [
    'header_style',
    'suppress_errors',
    'strip_tags',
    'strip_placeholder_links',
    'bold_style',
    'italic_style',
    'remove_nodes',
    'hard_break',
    'list_item_style',
    'preserve_comments',
    'use_autolinks',
    'table_pipe_escape',
    'table_caption_side',
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function convert(string $html): string {
    if ($html === '') {
      return '';
    }
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $options = [];
    foreach (self::OPTION_KEYS as $key) {
      $value = $config->get($key);
      if ($value !== NULL) {
        $options[$key] = $value;
      }
    }
    try {
      $converter = new HtmlConverter($options);
      // Not registered by Environment::createDefaultEnvironment(), but needed
      // for the table_pipe_escape/table_caption_side options above to have any
      // effect.
      $converter->getEnvironment()->addConverter(new TableConverter());
      $markdown = $converter->convert($html);
    }
    catch (\Exception $e) {
      // Catch converter exceptions and re-throw them with our own type.
      throw new HtmlToMarkdownConverterException($e->getMessage(), $e->getCode(), $e);
    }
    // strip_whitespace is not a league/html-to-markdown option; it's an
    // AI module post-processing step applied after conversion.
    if ($config->get('strip_whitespace') ?? TRUE) {
      $markdown = $this->stripExcessWhitespace($markdown);
    }

    return $markdown;
  }

}
