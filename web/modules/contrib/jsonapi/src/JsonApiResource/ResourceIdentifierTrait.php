<?php

namespace Drupal\jsonapi\JsonApiResource;

/**
 * Used to associate an object like an exception to a particular resource.
 *
 * @internal
 */
trait ResourceIdentifierTrait {

  /**
   * A ResourceIdentifier object.
   *
   * @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifier
   */
  protected $resourceIdentifier;

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

}
