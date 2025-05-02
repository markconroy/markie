<?php

namespace Drupal\entity_usage\Events;

use Drupal\Component\EventDispatcher\Event;

/**
 * Implementation of Entity Usage events.
 */
class EntityUsageEvent extends Event {

  /**
   * The target entity ID.
   *
   * @var int|string|null
   */
  protected $targetEntityId;

  /**
   * The target entity type.
   *
   * @var string|null
   */
  protected $targetEntityType;

  /**
   * The source entity ID.
   *
   * @var int|string|null
   */
  protected $sourceEntityId;

  /**
   * The source entity type.
   *
   * @var string|null
   */
  protected $sourceEntityType;

  /**
   * The source entity language code.
   *
   * @var string|null
   */
  protected $sourceEntityLangcode;

  /**
   * The source entity revision ID.
   *
   * @var int|string|null
   */
  protected $sourceEntityRevisionId;

  /**
   * The method used to relate source entity with the target entity.
   *
   * @var string|null
   */
  protected $method;

  /**
   * The name of the field in the source entity using the target entity.
   *
   * @var string|null
   */
  protected $fieldName;

  /**
   * The number of references to add or remove.
   *
   * @var int|null
   */
  protected $count;

  /**
   * EntityUsageEvents constructor.
   *
   * @param int|string|null $target_id
   *   The target entity ID.
   * @param string|null $target_type
   *   The target entity type.
   * @param int|string|null $source_id
   *   The source entity ID.
   * @param string|null $source_type
   *   The source entity type.
   * @param string|null $source_langcode
   *   The source entity language code.
   * @param int|string|null $source_vid
   *   The source entity revision ID.
   * @param string|null $method
   *   The method or way the two entities are being referenced.
   * @param string|null $field_name
   *   The name of the field in the source entity using the target entity.
   * @param int|null $count
   *   The number of references to add or remove.
   */
  public function __construct($target_id = NULL, $target_type = NULL, $source_id = NULL, $source_type = NULL, $source_langcode = NULL, $source_vid = NULL, $method = NULL, $field_name = NULL, $count = NULL) {
    $this->targetEntityId = $target_id;
    $this->targetEntityType = $target_type;
    $this->sourceEntityId = $source_id;
    $this->sourceEntityType = $source_type;
    $this->sourceEntityLangcode = $source_langcode;
    $this->sourceEntityRevisionId = $source_vid;
    $this->method = $method;
    $this->fieldName = $field_name;
    $this->count = $count;
  }

  /**
   * Sets the target entity id.
   *
   * @param int|string $id
   *   The target entity id.
   */
  public function setTargetEntityId($id): void {
    $this->targetEntityId = $id;
  }

  /**
   * Sets the target entity type.
   *
   * @param string $type
   *   The target entity type.
   */
  public function setTargetEntityType($type): void {
    $this->targetEntityType = $type;
  }

  /**
   * Sets the source entity id.
   *
   * @param int $id
   *   The source entity id.
   */
  public function setSourceEntityId($id): void {
    $this->sourceEntityId = $id;
  }

  /**
   * Sets the source entity type.
   *
   * @param string $type
   *   The source entity type.
   */
  public function setSourceEntityType($type): void {
    $this->sourceEntityType = $type;
  }

  /**
   * Sets the source entity language code.
   *
   * @param string $langcode
   *   The source entity language code.
   */
  public function setSourceEntityLangcode($langcode): void {
    $this->sourceEntityLangcode = $langcode;
  }

  /**
   * Sets the source entity revision ID.
   *
   * @param int $vid
   *   The source entity revision ID.
   */
  public function setSourceEntityRevisionId($vid): void {
    $this->sourceEntityRevisionId = $vid;
  }

  /**
   * Sets the method used to relate source entity with the target entity.
   *
   * @param string $method
   *   The source method.
   */
  public function setMethod($method): void {
    $this->method = $method;
  }

  /**
   * Sets the field name.
   *
   * @param string $field_name
   *   The field name.
   */
  public function setFieldName($field_name): void {
    $this->fieldName = $field_name;
  }

  /**
   * Sets the count.
   *
   * @param int $count
   *   The number od references to add or remove.
   */
  public function setCount($count): void {
    $this->count = $count;
  }

  /**
   * Gets the target entity id.
   *
   * @return int|string|null
   *   The target entity id or NULL.
   */
  public function getTargetEntityId() {
    return $this->targetEntityId;
  }

  /**
   * Gets the target entity type.
   *
   * @return null|string
   *   The target entity type or NULL.
   */
  public function getTargetEntityType() {
    return $this->targetEntityType;
  }

  /**
   * Gets the source entity id.
   *
   * @return int|string|null
   *   The source entity id or NULL.
   */
  public function getSourceEntityId() {
    return $this->sourceEntityId;
  }

  /**
   * Gets the source entity type.
   *
   * @return null|string
   *   The source entity type or NULL.
   */
  public function getSourceEntityType() {
    return $this->sourceEntityType;
  }

  /**
   * Gets the source entity language code.
   *
   * @return null|string
   *   The source entity language code or NULL.
   */
  public function getSourceEntityLangcode() {
    return $this->sourceEntityLangcode;
  }

  /**
   * Gets the source entity revision ID.
   *
   * @return int|string|null
   *   The source entity revision ID or NULL.
   */
  public function getSourceEntityRevisionId() {
    return $this->sourceEntityRevisionId;
  }

  /**
   * Gets the method used to relate source entity with the target entity.
   *
   * @return null|string
   *   The method or NULL.
   */
  public function getMethod() {
    return $this->method;
  }

  /**
   * Gets the field name.
   *
   * @return null|string
   *   The field name or NULL.
   */
  public function getFieldName() {
    return $this->fieldName;
  }

  /**
   * Gets the count.
   *
   * @return null|int
   *   The number of references to add or remove or NULL.
   */
  public function getCount() {
    return $this->count;
  }

}
