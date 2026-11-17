<?php

namespace Drupal\key;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Provides key overrides for configuration.
 */
class KeyConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * Mapping, or NULL if it has not been built yet.
   *
   * @var array|null
   */
  protected $mapping = NULL;

  /**
   * In override.
   *
   * @var bool
   */
  protected $inOverride = FALSE;

  /**
   * Creates a new ModuleConfigOverrides instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   */
  public function __construct(ConfigFactoryInterface $config_factory, CacheBackendInterface $cache_backend) {
    $this->configFactory = $config_factory;
    $this->cacheBackend = $cache_backend;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    if ($this->inOverride) {
      return [];
    }
    $this->inOverride = TRUE;

    // Always clear the flag, including on early returns and exceptions.
    // Otherwise the service would stop applying overrides for the rest of
    // the request.
    try {
      $mapping = $this->getMapping();
      if (!$mapping) {
        return [];
      }

      try {
        // @phpstan-ignore-next-line
        $storage = \Drupal::entityTypeManager()->getStorage('key');
      }
      catch (\Exception $e) {
        return [];
      }

      $overrides = [];

      foreach ($names as $name) {
        if (!array_key_exists($name, $mapping)) {
          continue;
        }

        $override = [];

        foreach ($mapping[$name] as $config_item => $key_id) {
          $key_storage = $storage->load($key_id);
          $key_value = $key_storage ? $key_storage->getKeyValue() : NULL;

          if (!isset($key_value)) {
            continue;
          }

          // Turn the dot-separated configuration item name into a nested
          // array and set the value.
          $config_item_parents = explode('.', $config_item);
          NestedArray::setValue($override, $config_item_parents, $key_value);
        }

        if ($override) {
          $overrides[$name] = $override;
        }
      }

      return $overrides;
    }
    finally {
      $this->inOverride = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'key_config_override';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * Get a mapping of key configuration overrides.
   *
   * @return array
   *   A mapping of key configuration overrides.
   */
  protected function getMapping() {
    if ($this->mapping !== NULL) {
      return $this->mapping;
    }

    // Overrides are rarely changed, so persist the mapping rather than listing
    // and loading every override in each request. Saving or deleting an
    // override invalidates the list cache tag.
    $cid = 'key_config_override_mapping';
    if ($cache = $this->cacheBackend->get($cid)) {
      $this->mapping = $cache->data;
      return $this->mapping;
    }

    $mapping = [];
    $override_ids = $this->configFactory->listAll('key.config_override.');
    $overrides = $this->configFactory->loadMultiple($override_ids);

    foreach ($overrides as $id => $override) {
      $override = $override->get();

      $config_id = '';
      if (!empty($override['config_prefix'])) {
        $config_id .= $override['config_prefix'] . '.';
      }
      if (isset($override['config_name'])) {
        $config_id .= $override['config_name'];
      }

      $config_item = $override['config_item'];
      $key_id = $override['key_id'];

      $mapping[$config_id][$config_item] = $key_id;
    }

    $this->cacheBackend->set($cid, $mapping, CacheBackendInterface::CACHE_PERMANENT, ['config:key_config_override_list']);
    $this->mapping = $mapping;

    return $this->mapping;
  }

  /**
   * Resets the statically cached mapping of key configuration overrides.
   */
  public function resetMapping() {
    $this->mapping = NULL;
  }

}
