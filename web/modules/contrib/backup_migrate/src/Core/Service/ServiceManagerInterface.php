<?php

namespace Drupal\backup_migrate\Core\Service;

/**
 * Simple interface for managing the service manager.
 *
 * @package Drupal\backup_migrate\Core\Environment
 */
interface ServiceManagerInterface {

  /**
   * Retrieve a service from the locator.
   *
   * @param string $type
   *   The type.
   *   The service type identifier.
   *
   * @return mixed
   *   The return value.
   */
  public function get($type);

  /**
   * Get an array of keys for all available services.
   *
   * @return array
   *   A render or configuration array.
   */
  public function keys();

  /**
   * Inject the services in this locator into the given plugin.
   *
   * @param object $plugin
   *   The plugin.
   *
   * @return mixed
   *   The return value.
   */
  public function addClient($plugin);

}
