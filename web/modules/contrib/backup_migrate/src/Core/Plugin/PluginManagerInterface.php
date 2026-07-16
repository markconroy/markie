<?php

namespace Drupal\backup_migrate\Core\Plugin;

use Drupal\backup_migrate\Core\Config\ConfigInterface;

/**
 * Manage all of the available Plugins.
 */
interface PluginManagerInterface {

  /**
   * Add an item to the manager.
   *
   * @param string $id
   *   The identifier.
   * @param \Drupal\backup_migrate\Core\Plugin\PluginInterface|object $item
   *   The item.
   *   The source to add.
   *
   * @return mixed
   *   The return value.
   */
  public function add($id, PluginInterface $item);

  /**
   * Get the item with the given id.
   *
   * @param string $id
   *   The identifier.
   *
   * @return \Drupal\backup_migrate\Core\Plugin\PluginInterface
   *   The requested integer.
   *   The item specified by the id or NULL if it doesn't exist.
   */
  public function get($id);

  /**
   * Get a list of all of the items.
   *
   * @return \Drupal\backup_migrate\Core\Plugin\PluginInterface[]
   *   *   An ordered list of the sources, keyed by their id.
   */
  public function getAll();

  /**
   * Set the configuration for all plugins.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface $config
   *   A configuration object containing only configuration for all plugins.
   */
  public function setConfig(ConfigInterface $config);

  /**
   * Get all plugins that implement the given operation.
   *
   * @param string $op
   *   The op.
   *   The name of the operation.
   *
   * @return \Drupal\backup_migrate\Core\Plugin\PluginInterface[]
   *   The requested integer.
   */
  public function getAllByOp($op);

  /**
   * Call all plugins which support the given operation.
   *
   * If an operand is used it is passed to each operator and should be returned
   * by each one. Not all operations require an operand in which case this will
   * be NULL.
   *
   * Params is an array of extra params which may be used. Plugins should expect
   * these to be passed as a keyed array.
   *
   * @param string $op
   *   The op.
   *   The name of the operation to be called.
   * @param mixed $operand
   *   The operand.
   *   If there in an object being operated on (eg. a backup file) it will be
   *    passed to each plugin in succession. If not then this will be NULL.
   * @param array $params
   *   The message parameters.
   *   Optional operation parameters as a key/value array.
   *
   * @return mixed
   *   The return value.
   */
  public function call($op, $operand = NULL, array $params = []);

  /**
   * Call all plugins which support the given operation.
   *
   * Params is an array of extra params which may be used. Plugins should expect
   * these to be passed as a keyed array.
   *
   * @param string $op
   *   The name of the operation to be called.
   * @param array $params
   *   Optional operation parameters as a key/value array.
   *
   * @return array
   *   *   The results in an array keyed by the plugin id.
   */
  public function map($op, array $params = []);

}
