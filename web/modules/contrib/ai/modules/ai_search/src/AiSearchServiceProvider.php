<?php

namespace Drupal\ai_search;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overrides the Search API Index Batch Helper service.
 */
class AiSearchServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('search_api.indexing_batch_helper')) {
      $definition = $container->getDefinition('search_api.indexing_batch_helper');
      $definition->setClass('Drupal\ai_search\Utility\AiSearchIndexingBatchHelper');

      // Add the database service as a new argument. This will be the last
      // argument passed to the new constructor.
      $definition->addArgument(new Reference('database'));
    }
  }

}
