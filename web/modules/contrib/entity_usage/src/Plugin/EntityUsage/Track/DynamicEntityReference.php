<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\entity_usage\EntityUsageTrackBase;
use Drupal\entity_usage\EntityUsageTrackMultipleLoadInterface;

/**
 * Tracks usage of entities related in dynamic_entity_reference fields.
 *
 * @EntityUsageTrack(
 *   id = "dynamic_entity_reference",
 *   label = @Translation("Dynamic Entity Reference"),
 *   description = @Translation("Tracks relationships created with 'Dynamic Entity Reference' fields."),
 *   field_types = {"dynamic_entity_reference"},
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class DynamicEntityReference extends EntityUsageTrackBase implements EntityUsageTrackMultipleLoadInterface {

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
    $entity_ids = [];
    if ($field_item instanceof FieldItemInterface) {
      $iterable = [$field_item];
    }
    else {
      $iterable = &$field;
    }

    foreach ($iterable as $item) {
      /** @var \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item */
      $item_value = $item->get('target_id')->getValue();
      $target_type_id = $item->get('target_type')->getValue();

      if (!empty($item_value)) {
        $entity_ids[$target_type_id][] = $item_value;
      }
    }

    $return = [];
    foreach ($entity_ids as $target_type_id => $entity_id_values) {
      $return = array_merge($return, $this->checkAndPrepareEntityIds($target_type_id, $entity_id_values, 'id'));
    }
    return $return;
  }

}
