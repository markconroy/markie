<?php

namespace Drupal\jsonapi\Revisions;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a revision ID implementation for entity revision ID values.
 *
 * @internal
 */
class VersionById extends NegotiatorBase implements VersionNegotiatorInterface {

  /**
   * {@inheritdoc}
   */
  protected function getRevisionId(EntityInterface $entity, $version_argument) {
    if (!is_numeric($version_argument)) {
      throw new InvalidVersionIdentifierException('The revision ID must be an integer.');
    }
    return $version_argument;
  }

}
