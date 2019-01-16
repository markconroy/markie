<?php

namespace Drupal\jsonapi\JsonApiResource;

/**
 * An interface for identifying a related resource.
 *
 * Implement this interface when an object is a stand-in for an Entity object.
 * For example, \Drupal\jsonapi\Exception\EntityAccessDeniedHttpException
 * implements this interface because it often replaces an entity in an
 * EntityCollection.
 *
 * @internal
 */
interface ResourceIdentifierInterface {

  /**
   * Gets the resource identifier's ID.
   *
   * @return string
   *   A resource ID.
   */
  public function getId();

  /**
   * Gets the resource identifier's JSON:API resource type name.
   *
   * @return string
   *   The JSON:API resource type name.
   */
  public function getTypeName();

}
