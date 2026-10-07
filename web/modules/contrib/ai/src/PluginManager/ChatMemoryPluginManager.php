<?php

namespace Drupal\ai\PluginManager;

use Drupal\ai\Attribute\ChatMemory;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages ChatMemory plugins.
 *
 * @see \Drupal\ai\Attribute\ChatMemory
 * @see \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface
 * @see plugin_api
 */
class ChatMemoryPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ChatMemoryPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ChatMemory', $namespaces, $module_handler, ChatMemoryInterface::class, ChatMemory::class);
    $this->setCacheBackend($cache_backend, 'chat_memory_plugins');
    $this->alterInfo('chat_memory_info');
  }

}
