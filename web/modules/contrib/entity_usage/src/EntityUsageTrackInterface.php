<?php

namespace Drupal\entity_usage;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines the interface for entity_usage track methods.
 *
 * Track plugins use any arbitrary method to link two entities together.
 * Examples include:
 *
 * - Entities related through an entity_reference field are tracked using the
 *   "entity_reference" method.
 * - Entities embedded into other entities are tracked using the "embed" method.
 *
 * Note that plugins extending this interface have to be performant.
 * Constructing the entity_usage table for large sites can take a long time and
 * involve millions of calls to ::getTargetEntities(). Best practice is to:
 * - Use entity queries over entity loading to check existence. For example, use
 *   the \Drupal\entity_usage\EntityUsageTrackBase::checkAndPrepareEntityIds()
 *   helper method to do this.
 * - If the field you are tracking supports multiple entities then check the
 *   existence for all entities are the same time. For example, use
 *   the \Drupal\entity_usage\EntityUsageTrackBase::checkAndPrepareEntityIds()
 *   helper method to do this.
 * - Before doing any entity queries or entity loading check that the entity
 *   type is being tracked. The helper method
 *   \Drupal\entity_usage\EntityUsageTrackBase::isEntityTypeTracked() can do
 *   this.
 * - If the plugin can be coded to process multiple cardinality fields
 *   efficiently, implement
 *   \Drupal\entity_usage\EntityUsageTrackMultipleLoadInterface to process all
 *   field values together. See
 *   \Drupal\entity_usage\Plugin\EntityUsage\Track\EntityReference as an
 *   example.
 */
interface EntityUsageTrackInterface extends PluginInspectionInterface {

  /**
   * Returns the tracking method unique id.
   *
   * @return string
   *   The tracking method id.
   */
  public function getId(): string;

  /**
   * Returns the tracking method label.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The tracking method label.
   */
  public function getLabel(): string|TranslatableMarkup;

  /**
   * Returns the tracking method description.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The tracking method description, or an empty string is non defined.
   */
  public function getDescription(): string|TranslatableMarkup;

  /**
   * Returns the field types this plugin is capable of tracking.
   *
   * @return string[]
   *   An indexed array of field type names, as defined in the plugin's
   *   annotation under the key "field_types".
   */
  public function getApplicableFieldTypes(): array;

  /**
   * Track usage updates on the creation of entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   */
  public function trackOnEntityCreation(EntityInterface $source_entity): void;

  /**
   * Track usage updates on the edition of entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity.
   */
  public function trackOnEntityUpdate(EntityInterface $source_entity): void;

  /**
   * Retrieve fields of the given types on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source_entity
   *   The source entity object.
   * @param string[] $field_types
   *   A list of field types.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   An array of fields that could reference to other content entities.
   */
  public function getReferencingFields(EntityInterface $source_entity, array $field_types): array;

  /**
   * Retrieve the target entity(ies) from a field item value.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The field item to get the target entity(ies) from.
   *
   * @return string[]
   *   An indexed array of strings where each target entity type and ID are
   *   concatenated with a "|" character. Will return an empty array if no
   *   target entity could be retrieved from the received field item value.
   */
  public function getTargetEntities(FieldItemInterface $item): array;

  /**
   * Updates the track usage data for an entity field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $source_entity
   *   The source entity.
   * @param string $field_name
   *   The field name.
   */
  public function updateTrackingDataForField(FieldableEntityInterface $source_entity, string $field_name): void;

}
