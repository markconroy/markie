<?php

namespace Drupal\metatag;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * A Plugin to manage your meta tag group.
 */
class MetatagGroupPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $subdir = 'Plugin/metatag/Group';

    // The name of the annotation class that contains the plugin definition.
    $annotation_namespace = 'Drupal\metatag\Annotation\MetatagGroup';
    $attribute_namespace = 'Drupal\metatag\Attribute\MetatagGroup';

    parent::__construct($subdir, $namespaces, $module_handler, NULL, $attribute_namespace, $annotation_namespace);

    $this->alterInfo('metatag_groups');

    $this->setCacheBackend($cache_backend, 'metatag_groups');
  }

}
