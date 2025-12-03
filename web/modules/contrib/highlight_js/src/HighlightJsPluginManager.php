<?php

namespace Drupal\highlight_js;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Highlight js plugin manager.
 */
class HighlightJsPluginManager extends DefaultPluginManager {

  /**
   * Constructs Highlight Js Plugin Manager.
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
    parent::__construct(
      'Plugin/HighlightJs',
      $namespaces,
      $module_handler,
      'Drupal\highlight_js\HighlightJsInterface',
      'Drupal\highlight_js\Annotation\HighlightJs'
    );
    $this->alterInfo('highlight_js_info');
    $this->setCacheBackend($cache_backend, 'highlight_js_plugins');
  }

}
