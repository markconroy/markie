<?php

namespace Drupal\metatag;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * A Plugin to manage your meta tag type.
 */
class MetatagTagPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $subdir = 'Plugin/metatag/Tag';

    // The name of the annotation class that contains the plugin definition.
    $annotation_namespace = 'Drupal\metatag\Annotation\MetatagTag';
    $attribute_namespace = 'Drupal\metatag\Attribute\MetatagTag';

    parent::__construct($subdir, $namespaces, $module_handler, NULL, $attribute_namespace, $annotation_namespace);

    $this->alterInfo('metatag_tags');

    $this->setCacheBackend($cache_backend, 'metatag_tags');
  }

}
