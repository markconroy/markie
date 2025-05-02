<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_usage\EntityUsageTrackBase;
use Drupal\entity_usage\EntityUsageTrackMultipleLoadInterface;

/**
 * Tracks usage of entities related in entity_reference fields.
 *
 * @EntityUsageTrack(
 *   id = "entity_reference",
 *   label = @Translation("Entity Reference"),
 *   description = @Translation("Tracks relationships created with 'Entity Reference' fields."),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *     "entity_reference_entity_modify",
 *     "file",
 *     "image",
 *     "webform",
 *   },
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class EntityReference extends EntityUsageTrackBase implements EntityUsageTrackMultipleLoadInterface {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    return $this->doGetTargetEntities($item->getParent(), $item);
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntitiesFromField(FieldItemListInterface $field): array {
    return $this->doGetTargetEntities($field);
  }

  /**
   * Retrieve the target entity(ies) from a field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to get the target entity(ies) from.
   * @param \Drupal\Core\Field\FieldItemInterface|null $field_item
   *   (optional) The field item to get the target entity(ies) from.
   *
   * @return string[]
   *   An indexed array of strings where each target entity type and ID are
   *   concatenated with a "|" character. Will return an empty array if no
   *   target entity could be retrieved from the received field item value.
   */
  private function doGetTargetEntities(FieldItemListInterface $field, ?FieldItemInterface $field_item = NULL): array {
    $target_type_id = $field->getFieldDefinition()->getSetting('target_type');
    // Check if target entity type is enabled, all entity types are enabled by
    // default.
    if (!$this->isEntityTypeTracked($target_type_id)) {
      return [];
    }

    $entity_ids = [];
    if ($field_item instanceof FieldItemInterface) {
      $item_value = $field_item->get('target_id')->getValue();
      if (!empty($item_value)) {
        $entity_ids[] = $item_value;
      }
    }
    else {
      foreach ($field as $item) {
        /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
        $item_value = $item->get('target_id')->getValue();
        if (!empty($item_value)) {
          $entity_ids[] = $item_value;
        }
      }
    }

    return $this->checkAndPrepareEntityIds($target_type_id, $entity_ids, 'id');
  }

}
