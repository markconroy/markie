<?php

namespace Drupal\jsonapi\JsonApiResource;

/**
 * Used to associate an object like an exception to a particular resource.
 *
 * @internal
 *
 * @see \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface
 */
trait ResourceIdentifierTrait {

  /**
   * A ResourceIdentifier object.
   *
   * @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier
   */
  protected $resourceIdentifier;

  /**
   * The JSON:API resource type of of the identified resource object.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $resourceType;

  /**
   * {@inheritdoc}
   */
  public function getId() {
    return $this->resourceIdentifier->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName() {
    return $this->resourceIdentifier->getTypeName();
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceType() {
    if (!isset($this->resourceType)) {
      $this->resourceType = $this->resourceIdentifier->getResourceType();
    }
    return $this->resourceType;
  }

}
