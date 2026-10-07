<?php

declare(strict_types=1);

namespace Drupal\ai;

use Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Swaps optional AI services for integrations when they are available.
 */
class AiServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // If the markdownify module is installed, reuse its HTML to Markdown
    // converter service instead of the AI module's own default converter,
    // so that markdownify's plugins and settings are honored.
    if ($container->has('markdownify.html_converter')) {
      $container->getDefinition('ai.html_to_markdown_converter')
        ->setClass(MarkdownifyHtmlToMarkdownAdapter::class)
        ->setArguments([
          new Reference('markdownify.html_converter'),
          new Reference('config.factory'),
        ]);
    }
  }

}
