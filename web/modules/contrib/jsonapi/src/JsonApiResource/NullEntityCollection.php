<?php

namespace Drupal\jsonapi\JsonApiResource;

/**
 * Use when there are no included resources but an EntityCollection is required.
 *
 * @internal
 */
class NullEntityCollection extends EntityCollection {

  /**
   * NullEntityCollection constructor.
   */
  public function __construct() {
    parent::__construct([]);
  }

}
