<?php

namespace Drupal\entity_usage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\TranslatableInterface;

/**
 * The entity update manager.
 *
 * @package Drupal\entity_usage
 */
class EntityUpdateManager implements EntityUpdateManagerInterface {

  /**
   * The usage track service.
   *
   * @var \Drupal\entity_usage\EntityUsageInterface
   */
  protected $usageService;

  /**
   * The usage track manager.
   *
   * @var \Drupal\entity_usage\EntityUsageTrackManager
   */
  protected $trackManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a new \Drupal\entity_usage\EntityUpdateManager object.
   *
   * @param \Drupal\entity_usage\EntityUsageInterface $usage_service
   *   The usage tracking service.
   * @param \Drupal\entity_usage\EntityUsageTrackManager $track_manager
   *   The PluginManager track service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\entity_usage\PreSaveUrlRecorder|null $preSaveUrlRecorder
   *   The presave URL recorder.
   * @param \Drupal\entity_usage\RecreateTrackingDataForFieldQueuer|null $recreateTrackingDataForFieldQueuer
   *   The recreate tracking data for field queuer.
   */
  public function __construct(
    EntityUsageInterface $usage_service,
    EntityUsageTrackManager $track_manager,
    ConfigFactoryInterface $config_factory,
    protected ?PreSaveUrlRecorder $preSaveUrlRecorder = NULL,
    protected ?RecreateTrackingDataForFieldQueuer $recreateTrackingDataForFieldQueuer = NULL,
  ) {
    $this->usageService = $usage_service;
    $this->trackManager = $track_manager;
    $this->config = $config_factory->get('entity_usage.settings');
    if ($this->preSaveUrlRecorder === NULL) {
      // @phpstan-ignore-next-line
      $this->preSaveUrlRecorder = \Drupal::service(PreSaveUrlRecorder::class);
    }
    if ($this->recreateTrackingDataForFieldQueuer === NULL) {
      // @phpstan-ignore-next-line
      $this->recreateTrackingDataForFieldQueuer = \Drupal::service(RecreateTrackingDataForFieldQueuer::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackUpdateOnCreation(EntityInterface $entity): void {
    if (!$this->allowSourceEntityTracking($entity)) {
      return;
    }

    // Call all plugins that want to track entity usages. We need to call this
    // for all translations as well since Drupal stores new revisions for all
    // translations by default when saving an entity.
    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $translation_language) {
        if ($entity->hasTranslation($translation_language->getId())) {
          /** @var \Drupal\Core\Entity\EntityInterface $translation */
          $translation = $entity->getTranslation($translation_language->getId());
          foreach ($this->getEnabledPlugins() as $plugin) {
            $plugin->trackOnEntityCreation($translation);
          }
        }
      }
    }
    else {
      // Not translatable, just call the plugins with the entity itself.
      foreach ($this->getEnabledPlugins() as $plugin) {
        $plugin->trackOnEntityCreation($entity);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackUpdateOnEdition(EntityInterface $entity): void {
    // If the URL has changed and this entity can be a target then any tracking
    // info based on the old the URL needs to be updated.
    if ($this->allowTargetEntityTracking($entity) && ($previous_url = $this->preSaveUrlRecorder->getUrl($entity))) {
      if ($previous_url !== $entity->toUrl()->toString()) {
        foreach ($this->usageService->listSources($entity, FALSE) as $usage) {
          if (is_subclass_of($this->trackManager->getDefinition($usage['method'])['class'], EntityUsageTrackUrlUpdateInterface::class)) {
            $this->recreateTrackingDataForFieldQueuer->addRecord(
              $usage['source_type'],
              $usage['source_id'],
              $usage['source_vid'],
              $usage['method'],
              $usage['field_name']
            );
          }
        }
      }
    }

    if (!$this->allowSourceEntityTracking($entity)) {
      return;
    }

    // Call all plugins that want to track entity usages. We need to call this
    // for all translations as well since Drupal stores new revisions for all
    // translations by default when saving an entity.
    if ($entity instanceof TranslatableInterface) {
      foreach ($entity->getTranslationLanguages() as $translation_language) {
        if ($entity->hasTranslation($translation_language->getId())) {
          /** @var \Drupal\Core\Entity\ContentEntityInterface $translation */
          $translation = $entity->getTranslation($translation_language->getId());
          foreach ($this->getEnabledPlugins() as $plugin) {
            $plugin->trackOnEntityUpdate($translation);
          }
        }
      }
    }
    else {
      // Not translatable, just call the plugins with the entity itself.
      foreach ($this->getEnabledPlugins() as $plugin) {
        $plugin->trackOnEntityUpdate($entity);
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function trackUpdateOnDeletion(EntityInterface $entity, $type = 'default'): void {
    // When an entity is being deleted the logic is much simpler and we don't
    // even need to call the plugins. Just delete the records that affect this
    // entity both as target and source.
    switch ($type) {
      case 'revision':
        assert($entity instanceof RevisionableInterface);
        $this->usageService->deleteBySourceEntity($entity->id(), $entity->getEntityTypeId(), NULL, $entity->getRevisionId());
        break;

      case 'translation':
        $this->usageService->deleteBySourceEntity($entity->id(), $entity->getEntityTypeId(), $entity->language()->getId());
        break;

      case 'default':
        $this->usageService->deleteBySourceEntity($entity->id(), $entity->getEntityTypeId());
        $this->usageService->deleteByTargetEntity($entity->id(), $entity->getEntityTypeId());
        break;

      default:
        // We only accept one of the above mentioned types.
        throw new \InvalidArgumentException('EntityUpdateManager::trackUpdateOnDeletion called with unknown deletion type: ' . $type);
    }
  }

  /**
   * Checks if an entity is allowed to be tracked as source.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return bool
   *   Whether the entity can be tracked or not.
   */
  protected function allowSourceEntityTracking(EntityInterface $entity): bool {
    $allow_tracking = FALSE;
    $entity_type = $entity->getEntityType();
    $enabled_source_entity_types = $this->config->get('track_enabled_source_entity_types');
    if (!is_array($enabled_source_entity_types) && ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface'))) {
      // When no settings are defined, track all content entities by default.
      $allow_tracking = TRUE;
    }
    elseif (is_array($enabled_source_entity_types) && in_array($entity_type->id(), $enabled_source_entity_types, TRUE)) {
      $allow_tracking = TRUE;
    }
    return $allow_tracking;
  }

  /**
   * Checks if an entity is allowed to be tracked as target.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return bool
   *   Whether the entity can be tracked or not.
   */
  protected function allowTargetEntityTracking(EntityInterface $entity): bool {
    $enabled_target_entity_types = $this->config->get('track_enabled_target_entity_types');
    // Every entity type is tracked if not set.
    return !is_array($enabled_target_entity_types) || in_array($entity->getEntityTypeId(), $enabled_target_entity_types, TRUE);
  }

  /**
   * Gets the enabled tracking plugins, all plugins are enabled by default.
   *
   * @return array<string, \Drupal\entity_usage\EntityUsageTrackInterface>
   *   The enabled plugin instances keyed by plugin ID.
   */
  protected function getEnabledPlugins(): array {
    $all_plugin_ids = array_keys($this->trackManager->getDefinitions());
    $enabled_plugins = $this->config->get('track_enabled_plugins');
    $enabled_plugin_ids = is_array($enabled_plugins) ? $enabled_plugins : $all_plugin_ids;

    $plugins = [];
    foreach (array_intersect($all_plugin_ids, $enabled_plugin_ids) as $plugin_id) {
      $plugins[$plugin_id] = $this->trackManager->createInstance($plugin_id);
    }

    return $plugins;
  }

}
