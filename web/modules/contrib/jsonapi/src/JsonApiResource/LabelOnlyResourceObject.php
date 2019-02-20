<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Core\Entity\EntityInterface;

/**
 * Value object decorating a ResourceObject; only its label is available.
 *
 * @internal
 */
final class LabelOnlyResourceObject extends ResourceObject {

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
   * {@inheritdoc}
   */
  protected function extractFields(EntityInterface $entity) {
    $fields = parent::extractFields($entity);
    $public_label_field_name = $this->resourceType->getPublicName($this->getLabelFieldName());
    return array_intersect_key($fields, [$public_label_field_name => TRUE]);
  }

}
