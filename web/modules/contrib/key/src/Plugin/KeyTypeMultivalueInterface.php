<?php

namespace Drupal\key\Plugin;

/**
 * Provides an interface for Key Type plugins that support multivalue keys.
 */
interface KeyTypeMultivalueInterface {

  /**
   * Serialize an array of key values into a string.
   *
   * @param array $array
   *   An array of key values.
   *
   * @return string
   *   A serialized string of key values.
   */
  public function serialize(array $array);

  /**
   * Unserialize a string of key values into an array.
   *
   * @param string|null $value
   *   A serialized string of key values, or NULL if the key has no value.
   *
   * @return array|null
   *   An array of key values, or NULL if the value is empty or cannot be
   *   unserialized.
   */
  public function unserialize($value);

}
