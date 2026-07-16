<?php

namespace Drupal\backup_migrate\Core\Plugin;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Config\ConfigInterface;
use Drupal\backup_migrate\Core\Config\ConfigurableInterface;
use Drupal\backup_migrate\Core\Config\ConfigurableTrait;
use Drupal\backup_migrate\Core\Service\ServiceManager;
use Drupal\backup_migrate\Core\Service\ServiceManagerInterface;

/**
 * Provides the plugin manager class.
 *
 * @package Drupal\backup_migrate\Core\Plugin
 */
class PluginManager implements PluginManagerInterface, ConfigurableInterface {
  use ConfigurableTrait;

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Plugin\PluginInterface[] The items
   */
  protected $items;

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Service\ServiceManagerInterface The services
   */
  protected $services;

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\File\TempFileManagerInterface The temp file manager
   */
  protected $tempFileManager;

  /**
   * Handles the construct operation.
   *
   * @param \Drupal\backup_migrate\Core\Service\ServiceManagerInterface $services
   *   The services.
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface $config
   *   The configuration values.
   */
  public function __construct(ServiceManagerInterface|null $services = NULL, ConfigInterface|null $config = NULL) {
    // Add the injected service locator for dependency injection into plugins.
    $this->setServiceManager($services ? $services : new ServiceManager());

    // Set the configuration or a null object if no config was specified.
    $this->setConfig($config ? $config : new Config());

    // Create an array to store the plugins themselves.
    $this->items = [];
  }

  /**
   * Set the configuration. Reconfigure all of the installed plugins.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface $config
   *   The configuration values.
   */
  public function setConfig(ConfigInterface $config) {
    // Set the configuration object to the one passed in.
    $this->config = $config;

    // Pass the appropriate configuration to each of the installed plugins.
    foreach ($this->getAll() as $key => $plugin) {
      $this->configurePlugin($plugin, $key);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function add($id, PluginInterface $item) {
    $this->preparePlugin($item, $id);
    $this->items[$id] = $item;
  }

  /**
   * {@inheritdoc}
   */
  public function get($id) {
    return $this->items[$id] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    return empty($this->items) ? [] : $this->items;
  }

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
  public function getAllByOp($op) {
    $out = [];
    $weights = [];

    foreach ($this->getAll() as $key => $plugin) {
      if ($plugin->supportsOp($op)) {
        $out[$key] = $plugin;
        $weights[$key] = $plugin->opWeight($op);
      }
    }
    array_multisort($weights, $out);
    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function call($op, $operand = NULL, array $params = []) {

    // Run each of the installed plugins which implements the given operation.
    foreach ($this->getAllByOp($op) as $plugin) {
      $operand = $plugin->{$op}($operand, $params);
    }

    return $operand;
  }

  /**
   * {@inheritdoc}
   */
  public function map($op, array $params = []) {
    $out = [];

    // Run each of the installed plugins which implements the given operation.
    foreach ($this->getAllByOp($op) as $key => $plugin) {
      $out[$key] = $plugin->{$op}($params);
    }

    return $out;
  }

  /**
   * Prepare the plugin for use.
   *
   * This is called when a plugin is added to the manager and it configures the
   * plugin according to the config object injected into the manager. It also
   * injects other dependencies as needed.
   *
   * @param \Drupal\backup_migrate\Core\Plugin\PluginInterface $plugin
   *   The plugin to prepare for use.
   * @param string $id
   *   The id of the plugin (to extract the correct settings).
   */
  protected function preparePlugin(PluginInterface $plugin, $id) {
    // If this plugin can be configured, then pass in the configuration.
    $this->configurePlugin($plugin, $id);

    // Inject the available services.
    $this->services()->addClient($plugin);
  }

  /**
   * Set the configuration for the given plugin.
   *
   * @param mixed $plugin
   *   The plugin.
   * @param string $id
   *   The identifier.
   */
  protected function configurePlugin(PluginInterface $plugin, $id) {
    // If this plugin can be configured, then pass in the configuration.
    if ($plugin instanceof ConfigurableInterface) {
      // Configure the plugin with the appropriate subset of the configuration.
      $config = (array) $this->confGet($id);

      // Set the config for the plugin.
      $plugin->setConfig(new Config($config));

      // Get the configuration back from the plugin to populate defaults within
      // the manager.
      $this->config()->set($id, $plugin->config());
    }
  }

  /**
   * Handles the services operation.
   *
   * @return \Drupal\backup_migrate\Core\Service\ServiceManagerInterface
   *   The requested integer.
   */
  public function services() {
    return $this->services;
  }

  /**
   * Sets the service manager.
   *
   * @param \Drupal\backup_migrate\Core\Service\ServiceManagerInterface $services
   *   The services.
   */
  public function setServiceManager(ServiceManagerInterface $services) {
    $this->services = $services;

    // Inject or re-inject the services.
    foreach ($this->getAll() as $plugin) {
      $this->services()->addClient($plugin);
    }
  }

}
