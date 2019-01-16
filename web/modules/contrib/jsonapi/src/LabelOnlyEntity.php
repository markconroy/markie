<?php

namespace Drupal\jsonapi;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifierTrait;

/**
 * Value object decorating an Entity object; only its label is to be normalized.
 *
 * @internal
 */
class LabelOnlyEntity implements CacheableDependencyInterface, ResourceIdentifierInterface {

  use CacheableDependencyTrait;
  use ResourceIdentifierTrait;

  /**
   * Constructs a LabelOnlyEntity value object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to only normalize its label.
   */
  public function __construct(EntityInterface $entity) {
    $this->resourceIdentifier = ResourceIdentifier::fromEntity($entity);
    $this->entity = $entity;
    $this->setCacheability($entity);
  }

  /**
   * Gets the decorated entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The label for which to only normalize its label.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Determines the entity type's (internal) label field name.
   */
  public function getLabelFieldName() {
    $label_field_name = $this->entity->getEntityType()->getKey('label');
    // @todo Remove this work-around after https://www.drupal.org/project/drupal/issues/2450793 lands.
    if ($this->entity->getEntityTypeId() === 'user') {
      $label_field_name = 'name';
    }
    return $label_field_name;
  }

}
