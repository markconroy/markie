<?php

namespace Drupal\entity_usage;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Defines the interface for entity_usage track methods.
 *
 * Track plugins use any arbitrary method to link two entities together.
 * Examples include:
 *
 * - Entities related through an entity_reference field are tracked using the
 *   "entity_reference" method.
 * - Entities embedded into other entities are tracked using the "embed" method.
 */
interface EntityUsageTrackMultipleLoadInterface extends EntityUsageTrackInterface {

  /**
   * Retrieve the target entity(ies) from a field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field to get the target entity(ies) from.
   *
   * @return string[]
   *   An indexed array of strings where each target entity type and ID are
   *   concatenated with a "|" character. Will return an empty array if no
   *   target entity could be retrieved from the received field item value.
   */
  public function getTargetEntitiesFromField(FieldItemListInterface $field): array;

}
