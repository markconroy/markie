<?php

namespace Drupal\backup_migrate\Core\Plugin;

/**
 * Implements the injection code for a PluginCallerInterface object.
 *
 * @package Drupal\backup_migrate\Core\Plugin
 */
trait PluginCallerTrait {

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Plugin\PluginManagerInterface The plugins
   */
  protected $plugins;

  /**
   * Inject the plugin manager.
   *
   * @param \Drupal\backup_migrate\Core\Plugin\PluginManagerInterface $plugins
   *   The plugins.
   */
  public function setPluginManager(PluginManagerInterface $plugins) {
    $this->plugins = $plugins;
  }

  /**
   * Get the plugin manager.
   *
   * @return \Drupal\backup_migrate\Core\Plugin\PluginManagerInterface
   *   The requested integer.
   */
  public function plugins() {
    // Return the list of plugins or a blank placeholder.
    return $this->plugins ? $this->plugins : new PluginManager();
  }

}
