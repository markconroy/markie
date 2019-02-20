<?php

namespace Drupal\jsonapi\Revisions;

/**
 * Used when a version ID is valid, but the requested version does not exist.
 *
 * @internal
 */
class VersionNotFoundException extends \InvalidArgumentException {

  /**
   * {@inheritdoc}
   */
  public function __construct($message = NULL, $code = 0, \Exception $previous = NULL) {
    parent::__construct(!is_null($message) ? $message : 'The identified version could not be found.', $code, $previous);
  }

}
