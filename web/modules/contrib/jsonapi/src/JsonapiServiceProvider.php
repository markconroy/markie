<?php

namespace Drupal\jsonapi;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\Core\StackMiddleware\NegotiationMiddleware;
use Drupal\jsonapi\DependencyInjection\Compiler\RegisterSerializationClassesCompilerPass;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds 'api_json' as known format and prevents its use in the REST module.
 *
 * @internal
 */
class JsonapiServiceProvider implements ServiceModifierInterface, ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // @todo Remove when we stop supporting Drupal 8.5.
    if (floatval(\Drupal::VERSION) < 8.6) {
      // Swap the cache service back.
      $definition = $container->getDefinition('jsonapi.resource_type.repository');
      $definition->setArgument(3, new Reference('cache.static'));
      $container->setDefinition('jsonapi.resource_type.repository', $definition);

      // Drop the new service definition.
      $container->removeDefinition('cache.jsonapi_resource_types');
    }

    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')->getClass(), NegotiationMiddleware::class, TRUE)) {
      // @see http://www.iana.org/assignments/media-types/application/vnd.api+json
      $container->getDefinition('http_middleware.negotiation')
        ->addMethodCall('registerFormat', [
          'api_json',
          ['application/vnd.api+json'],
        ])
        ->addMethodCall('registerFormat', [
          'bin',
          ['application/octet-stream'],
        ]);
    }

    // @todo Remove this when JSON:API requires Drupal >=8.6, see https://www.drupal.org/node/1927648.
    if (floatval(\Drupal::VERSION) < 8.6) {
      $container->removeDefinition('jsonapi.file_upload');
      $container->removeDefinition('file.uploader');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $container->addCompilerPass(new RegisterSerializationClassesCompilerPass());
  }

}
