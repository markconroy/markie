<?php

namespace Drupal\key;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a Key entity.
 */
interface KeyInterface extends ConfigEntityInterface {

  /**
   * Gets the description of the key.
   *
   * @return string
   *   The description of this key.
   */
  public function getDescription();

  /**
   * Returns the configured plugins for the key.
   *
   * @return \Drupal\key\Plugin\KeyPluginInterface[]
   *   An array of plugins, indexed by plugin type.
   */
  public function getPlugins();

  /**
   * Returns the configured plugin of the requested type.
   *
   * @param string $type
   *   The plugin type to return.
   *
   * @return \Drupal\key\Plugin\KeyPluginInterface
   *   The plugin.
   */
  public function getPlugin($type);

  /**
   * Sets a plugin of the requested type and plugin ID.
   *
   * @param string $type
   *   The plugin type.
   * @param string $id
   *   The plugin ID.
   */
  public function setPlugin($type, $id);

  /**
   * Returns the configured key type for the key.
   *
   * @return \Drupal\key\Plugin\KeyTypeInterface
   *   The key type associated with the key.
   */
  public function getKeyType();

  /**
   * Returns the configured key provider for the key.
   *
   * @return \Drupal\key\Plugin\KeyProviderInterface
   *   The key provider associated with the key.
   */
  public function getKeyProvider();

  /**
   * Returns the configured key input for the key.
   *
   * @return \Drupal\key\Plugin\KeyInputInterface
   *   The key input associated with the key.
   */
  public function getKeyInput();

  /**
   * Gets the value of the key.
   *
   * @param bool $reset
   *   Whether to bypass the value cached for the current request and retrieve
   *   the value from the key provider again.
   *
   * @return string|null
   *   The value of the key, or NULL if the key provider could not retrieve
   *   it.
   */
  public function getKeyValue($reset = FALSE);

  /**
   * Gets the values of the key.
   *
   * @param bool $reset
   *   Whether to bypass the value cached for the current request and retrieve
   *   the value from the key provider again.
   *
   * @return array
   *   The values of the key.
   */
  public function getKeyValues($reset = FALSE);

  /**
   * Sets the value of the key.
   *
   * Note, in very early 8.x-1.x versions of key, this method returned a value.
   * With issue #2693145, setting the key value is done directly and no longer
   * returns any value.
   *
   * An array may only be passed for a key whose key type supports multiple
   * values; it is serialized by the key type before being stored. The key
   * value is written to the key provider when the key is saved.
   *
   * @param string|array|null $key_value
   *   The key value to set: a string, or an array of values for a key whose
   *   key type supports multiple values. If NULL, no value is written on save.
   *
   * @throws \InvalidArgumentException
   *   Thrown if an array is passed for a key whose key type does not support
   *   multiple values.
   */
  public function setKeyValue($key_value);

}
