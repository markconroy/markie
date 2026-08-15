<?php

namespace Drupal\backup_migrate\Core\Config;

/**
 * Provides the validation error class.
 *
 * @package Drupal\backup_migrate\Core\Config
 */
class ValidationError implements ValidationErrorInterface {

  /**
   * Stores the value.
   *
   * @var string The field key
   */
  protected $fieldKey = '';

  /**
   * Stores the value.
   *
   * @var string The message
   */
  protected $message = '';

  /**
   * Stores the value.
   *
   * @var array The replacement
   */
  protected $replacement = [];

  /**
   * Handles the construct operation.
   *
   * @param mixed $field_key
   *   The field key.
   * @param string $message
   *   The message.
   * @param array $replacement
   *   The replacement.
   */
  public function __construct($field_key, $message, array $replacement = []) {
    $this->fieldKey = $field_key;
    $this->message = $message;
    $this->replacement = $replacement;
  }

  /**
   * Gets the message.
   *
   * @return string
   *   The requested string.
   */
  public function getMessage() {
    return $this->message;
  }

  /**
   * Gets the replacement.
   *
   * @return array
   *   A render or configuration array.
   */
  public function getReplacement() {
    return $this->replacement;
  }

  /**
   * Gets the field key.
   *
   * @return string
   *   The requested string.
   */
  public function getFieldKey() {
    return $this->fieldKey;
  }

  /**
   * String representation of the exception.
   *
   * @return string
   *   *   The string representation of the exception.
   */
  public function __toString() {
    return strtr($this->getMessage(), $this->getReplacement());
  }

}
