<?php

namespace Drupal\entity_usage;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\entity_usage\UrlToEntityIntegrations\PublicFileIntegration;
use Drupal\entity_usage\UrlToEntityIntegrations\RedirectIntegration;

/**
 * Defines a service provider for the Entity Usage module.
 */
class EntityUsageServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    if (isset($container->getParameter('container.modules')['redirect'])) {
      $container
        ->register(RedirectIntegration::class)
        ->setAutowired(TRUE)
        ->setAutoconfigured(TRUE);
    }

    if (isset($container->getParameter('container.modules')['file'])) {
      $container
        ->register(PublicFileIntegration::class)
        ->setAutowired(TRUE)
        ->setAutoconfigured(TRUE);
    }
  }

}
