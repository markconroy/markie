<?php

namespace Drupal\backup_migrate\Core\Config;

/**
 * Defines an interface for configuration validation errors.
 *
 * @package Drupal\backup_migrate\Core\Config
 */
interface ValidationErrorInterface {

  /**
   * Gets the message.
   *
   * @return string
   *   The requested string.
   */
  public function getMessage();

  /**
   * Gets the replacement.
   *
   * @return array
   *   A render or configuration array.
   */
  public function getReplacement();

  /**
   * Gets the field key.
   *
   * @return string
   *   The requested string.
   */
  public function getFieldKey();

}
