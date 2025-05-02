<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\entity_usage\EntityUsageTrackBase;
use Drupal\layout_builder\Plugin\Field\FieldType\LayoutSectionItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tracks usage of entities related in Layout Builder layouts.
 *
 * @EntityUsageTrack(
 *   id = "layout_builder",
 *   label = @Translation("Layout builder"),
 *   description = @Translation("Tracks relationships in layout builder layouts."),
 *   field_types = {"layout_section"},
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class LayoutBuilder extends EntityUsageTrackBase {

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->blockManager = $container->get('plugin.manager.block');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    assert($item instanceof LayoutSectionItem);

    // We support both Content Blocks and Entity Browser Blocks.
    $blockContentRevisionIds = [];
    $blockContentUuids = [];
    $ebbContentIds = [];
    $contentDependencyIds = [];

    /** @var \Drupal\layout_builder\Plugin\DataType\SectionData $value */
    foreach ($item as $value) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = $value->getValue();
      foreach ($section->getComponents() as $component) {
        $configuration = $component->toArray()['configuration'];
        try {
          ['id' => $pluginId] = $this->blockManager->getDefinition($component->getPluginId());
        }
        catch (PluginNotFoundException $e) {
          // Block has since been removed, continue.
          continue;
        }

        if ($pluginId === 'inline_block') {
          $blockContentRevisionIds[] = $configuration['block_revision_id'];
        }
        elseif ($pluginId === 'entity_browser_block' && !empty($configuration['entity_ids'])) {
          $ebbContentIds = array_unique(array_merge($ebbContentIds, (array) $configuration['entity_ids']));
        }
        elseif ($pluginId === 'block_content') {
          [, $uuid] = explode(':', $configuration['id']);
          $blockContentUuids[] = $uuid;
        }

        // Check the block plugin's content dependencies.
        /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
        $plugin = $component->getPlugin();
        // @todo there might be hidden entity loading in here.
        $dependencies = $plugin->calculateDependencies();
        if (!empty($dependencies['content'])) {
          $contentDependencyIds = array_merge($contentDependencyIds, $dependencies['content']);
        }
      }
    }

    $target_entities = [];
    if (count($blockContentRevisionIds) > 0) {
      $target_entities = $this->checkAndPrepareEntityIds('block_content', $blockContentRevisionIds, 'revision');
    }
    if (count($blockContentUuids) > 0) {
      $target_entities = array_merge($target_entities, $this->checkAndPrepareEntityIds('block_content', $blockContentUuids, 'uuid'));
    }
    if (count($ebbContentIds) > 0) {
      $target_entities = array_merge($target_entities, $this->prepareEntityBrowserBlockIds($ebbContentIds));
    }
    if (count($contentDependencyIds) > 0) {
      $target_entities = array_merge($target_entities, $this->prepareContentDependencyIds($contentDependencyIds));
    }
    return array_unique($target_entities);

  }

  /**
   * Prepare Entity Browser Block IDs to be in the correct format.
   *
   * @param array $ebbContentIds
   *   An array of entity ID values as returned from the EBB configuration.
   *   (Each value is expected to be in the format "node:123", "media:42", etc).
   *
   * @return array
   *   The same array passed in, with the following modifications:
   *   - Non-loadable entities will be filtered out.
   *   - The ":" character will be replaced by the "|" character.
   */
  private function prepareEntityBrowserBlockIds(array $ebbContentIds) {
    $return = $ids = [];

    // Keys the IDs by entity type.
    foreach ($ebbContentIds as $id) {
      // Entity Browser Block stores each entity in "entity_ids" in the format:
      // "{$entity_type_id}:{$entity_id}".
      [$entity_type_id, $entity_id] = explode(":", $id);
      $ids[$entity_type_id][] = $entity_id;
    }

    foreach ($ids as $entity_type_id => $entity_ids) {
      // Return items in the expected format, separating type and id with a "|".
      $return = array_merge(
        $return,
        $this->checkAndPrepareEntityIds($entity_type_id, $entity_ids, 'id')
      );
    }

    return $return;
  }

  /**
   * Prepare plugin content dependency IDs to be in the correct format.
   *
   * @param array $dependency_ids
   *   An array of entity ID values as returned from the plugin dependency
   *   configuration. (Each value is expected to be in the format
   *   "media:image:4dd39aa2-068f-11ec-9a03-0242ac130003", etc).
   *
   * @return array
   *   The same array passed in, with the following modifications:
   *   - Non-loadable entities will be filtered out.
   *   - The bundle ID in the middle will be removed.
   *   - The UUID will be converted to a regular ID.
   *   - The ":" character will be replaced by the "|" character.
   */
  private function prepareContentDependencyIds(array $dependency_ids) {
    $return = $ids = [];

    // Keys the UUIDs by entity type.
    foreach ($dependency_ids as $id) {
      // Content dependencies are stored in the format:
      // "{$entity_type_id}:{$bundle_id}:{$entity_uuid}".
      [$entity_type_id, , $entity_uuid] = explode(':', $id);
      $ids[$entity_type_id][] = $entity_uuid;
    }

    foreach ($ids as $entity_type_id => $entity_uuids) {
      $return = array_merge(
        $return,
        $this->checkAndPrepareEntityIds($entity_type_id, $entity_uuids, 'uuid')
      );
    }

    return $return;
  }

}
