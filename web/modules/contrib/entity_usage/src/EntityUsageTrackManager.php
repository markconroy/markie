<?php

namespace Drupal\entity_usage;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages entity_usage track plugins.
 *
 * @method \Drupal\entity_usage\EntityUsageTrackInterface createInstance($plugin_id, array $configuration = [])
 */
class EntityUsageTrackManager extends DefaultPluginManager {

  /**
   * A list of classes that sources entities can implement.
   *
   * @var class-string[]
   */
  protected array $sourceEntityClasses = [];

  /**
   * Constructs a new EntityUsageTrackManager.
   *
   * @param mixed[] $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/EntityUsage/Track', $namespaces, $module_handler, 'Drupal\entity_usage\EntityUsageTrackInterface', 'Drupal\entity_usage\Annotation\EntityUsageTrack');
    $this->alterInfo('entity_usage_track_info');
    $this->setCacheBackend($cache_backend, 'entity_usage_track_plugins');
  }

  /**
   * Determines if the tracking plugins support this entity type as a source.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type to check.
   *
   * @return bool
   *   TRUE if the entity type is supported as a source entity, FALSE if not.
   */
  public function isEntityTypeSource(EntityTypeInterface $entity_type): bool {
    if (empty($this->sourceEntityClasses)) {
      foreach ($this->getDefinitions() as $definition) {
        $this->sourceEntityClasses[] = $definition['source_entity_class'];
      }
      $this->sourceEntityClasses = array_unique($this->sourceEntityClasses);
    }
    foreach ($this->sourceEntityClasses as $source_entity_class) {
      if ($entity_type->entityClassImplements($source_entity_class)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions(): void {
    parent::clearCachedDefinitions();
    $this->sourceEntityClasses = [];
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);
    if (!isset($definition['source_entity_class'])) {
      @trigger_error(sprintf("The plugin definition '%s' not defining the 'source_entity_class' property is deprecated in entity_usage:8.x-2.0-beta20 and will cause an exception in entity_usage:8.x-2.1. See https://www.drupal.org/node/3505220", $definition['class']), E_USER_DEPRECATED);
      $definition['source_entity_class'] = EntityInterface::class;
    }
  }

}
