<?php

namespace Drupal\key\Entity;

use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Controller\KeyListBuilder;
use Drupal\key\Exception\KeyValueNotSetException;
use Drupal\key\Form\KeyAddForm;
use Drupal\key\Form\KeyDeleteForm;
use Drupal\key\Form\KeyEditForm;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyPluginCollection;
use Drupal\key\Plugin\KeyProviderSettableValueInterface;

/**
 * Defines the Key entity.
 *
 * The annotation is kept for Drupal 10, which has no ConfigEntityType
 * attribute. Keep both definitions in sync.
 *
 * @todo Remove the annotation when the minimum supported core version is
 *   Drupal 11.0 or later.
 *
 * @ConfigEntityType(
 *   id = "key",
 *   label = @Translation("Key"),
 *   module = "key",
 *   handlers = {
 *     "list_builder" = "Drupal\key\Controller\KeyListBuilder",
 *     "form" = {
 *       "add" = "Drupal\key\Form\KeyAddForm",
 *       "edit" = "Drupal\key\Form\KeyEditForm",
 *       "delete" = "Drupal\key\Form\KeyDeleteForm"
 *     }
 *   },
 *   config_prefix = "key",
 *   admin_permission = "administer keys",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   links = {
 *     "add-form" = "/admin/config/system/keys/add",
 *     "edit-form" = "/admin/config/system/keys/manage/{key}",
 *     "delete-form" = "/admin/config/system/keys/manage/{key}/delete",
 *     "collection" = "/admin/config/system/keys"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "key_type",
 *     "key_type_settings",
 *     "key_provider",
 *     "key_provider_settings",
 *     "key_input",
 *     "key_input_settings"
 *   }
 * )
 */
#[ConfigEntityType(
  id: 'key',
  label: new TranslatableMarkup('Key'),
  config_prefix: 'key',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
  ],
  handlers: [
    'list_builder' => KeyListBuilder::class,
    'form' => [
      'add' => KeyAddForm::class,
      'edit' => KeyEditForm::class,
      'delete' => KeyDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/config/system/keys/add',
    'edit-form' => '/admin/config/system/keys/manage/{key}',
    'delete-form' => '/admin/config/system/keys/manage/{key}/delete',
    'collection' => '/admin/config/system/keys',
  ],
  admin_permission: 'administer keys',
  config_export: [
    'id',
    'label',
    'description',
    'key_type',
    'key_type_settings',
    'key_provider',
    'key_provider_settings',
    'key_input',
    'key_input_settings',
  ],
)]
class Key extends ConfigEntityBase implements KeyInterface, EntityWithPluginCollectionInterface {

  /**
   * The key ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The key label.
   *
   * @var string
   */
  protected $label;

  /**
   * The key description.
   *
   * @var string
   */
  protected $description = '';

  /**
   * The types of plugins used by a the key entity.
   *
   * @var array
   */
  protected $pluginTypes = ['key_type', 'key_provider', 'key_input'];

  /**
   * The key type plugin id.
   *
   * @var string
   */
  protected $key_type = 'authentication';

  /**
   * The key provider plugin id.
   *
   * @var string
   */
  protected $key_provider = 'config';

  /**
   * The key input plugin id.
   *
   * @var string
   */
  protected $key_input = 'none';

  /**
   * The key type plugin settings.
   *
   * @var array
   */
  protected $key_type_settings = [];

  /**
   * The key provider plugin settings.
   *
   * @var array
   */
  protected $key_provider_settings = [];

  /**
   * The key input plugin settings.
   *
   * @var array
   */
  protected $key_input_settings = [];

  /**
   * The key value.
   *
   * @var string|null
   */
  protected $keyValue = NULL;

  /**
   * The plugin collections, indexed by plugin type.
   *
   * @var \Drupal\key\Plugin\KeyPluginCollection[]
   */
  protected $pluginCollections;

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Return the list of plugin types supported by key entities.
   *
   * @return array
   *   The list of plugin types.
   */
  public function getPluginTypes() {
    return $this->pluginTypes;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugins() {
    $plugins = [];
    foreach ($this->pluginTypes as $type) {
      $plugins[$type] = $this->getPlugin($type);
    }

    return $plugins;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlugin($type) {
    return $this->getPluginCollection($type)->get($this->$type);
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Set plugin'))]
  public function setPlugin($type, $id) {
    $this->$type = $id;
    $this->getPluginCollection($type)->addInstanceId($id);
  }

  /**
   * Returns a list of plugins, for use in forms.
   *
   * @param string $type
   *   The plugin type to use.
   *
   * @return array
   *   The list of plugins, indexed by ID.
   */
  public function getPluginsAsOptions($type) {
    $manager = \Drupal::service("plugin.manager.key.$type");

    $options = [];
    foreach ($manager->getDefinitions() as $id => $definition) {
      $options[$id] = ($definition['label']);
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyType() {
    return $this->getPlugin('key_type');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyProvider() {
    return $this->getPlugin('key_provider');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyInput() {
    return $this->getPlugin('key_input');
  }

  /**
   * Create a plugin collection of the requested plugin type.
   *
   * @param string $type
   *   The plugin type.
   *
   * @return \Drupal\key\Plugin\KeyPluginCollection
   *   The plugin collection.
   */
  public function getPluginCollection($type) {
    if (!isset($this->pluginCollections[$type . '_settings'])) {
      $this->pluginCollections[$type . '_settings'] = new KeyPluginCollection(
        \Drupal::service("plugin.manager.key.$type"),
        $this->get($type),
        $this->get($type . '_settings'));
    }

    return $this->pluginCollections[$type . '_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    $plugin_collections = [];
    foreach ($this->pluginTypes as $type) {
      $plugin_collections[$type . '_settings'] = $this->getPluginCollection($type);
    }

    return $plugin_collections;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue($reset = FALSE) {
    $memory_cache = $this->getMemoryCache();
    $cache_key = $this->getKeyCacheId();

    // If the key value has not already been retrieved during this request or
    // a reset was requested, retrieve the value using the key provider.
    $cache = $reset ? FALSE : $memory_cache->get($cache_key);
    if (!$cache) {
      $key_value = $this->getKeyProvider()->getKeyValue($this);
      $memory_cache->set($cache_key, $key_value);
      return $key_value;
    }

    return $cache->data;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValues($reset = FALSE) {
    $value = $this->getKeyValue($reset);
    $key_type = $this->getKeyType();

    if ($key_type->getPluginDefinition()['multivalue']['enabled']) {
      $values = $key_type->unserialize($value);
    }
    else {
      $values = (array) $value;
    }

    return $values;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Set key value'), pluralize: FALSE)]
  public function setKeyValue($key_value) {
    if (is_array($key_value)) {
      $key_type = $this->getKeyType();
      if (empty($key_type->getPluginDefinition()['multivalue']['enabled'])) {
        throw new \InvalidArgumentException(sprintf('The key type "%s" of key "%s" does not support multiple values, so the key value must be a string.', $key_type->getPluginId(), $this->id()));
      }
      $key_value = $key_type->serialize($key_value);
    }
    $this->keyValue = $key_value;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Delete key value'), pluralize: FALSE)]
  public function deleteKeyValue() {
    // Clear the cached key value.
    $this->invalidateKeyValueCache();

    if ($this->getKeyProvider() instanceof KeyProviderSettableValueInterface) {
      return $this->getKeyProvider()->deleteKeyValue($this);
    }
    else {
      return FALSE;
    }
  }

  /**
   * Helper method to get key cache id.
   *
   * @return string
   *   The cache key id.
   */
  protected function getKeyCacheId(): string {
    return 'key:value:' . $this->id();
  }

  /**
   * Gets the memory cache that holds key values for the current request.
   *
   * @return \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface
   *   The memory cache service.
   */
  protected function getMemoryCache(): MemoryCacheInterface {
    return \Drupal::service('key.memory_cache');
  }

  /**
   * Removes this key's value from the memory cache.
   */
  protected function invalidateKeyValueCache(): void {
    $this->getMemoryCache()->delete($this->getKeyCacheId());
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // If the key provider supports setting a key value.
    if ($this->getKeyProvider() instanceof KeyProviderSettableValueInterface && isset($this->keyValue)) {
      $this->getKeyProvider()->setKeyValue($this, $this->keyValue);
    }
    // If the key provider does not support setting a value.
    else {
      // If a key value was defined, throw an exception.
      if (isset($this->keyValue)) {
        throw new KeyValueNotSetException('The selected key provider does not support setting a key value.');
      }
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // The key value or the provider configuration may have changed, so the
    // value cached during this request is no longer reliable.
    $this->invalidateKeyValueCache();

    // Allow the key provider to perform post-save actions.
    $this->getKeyProvider()->postSave($this, $storage, $update);

    // @todo Remove the fallback to the original property when the minimum
    //   supported core version is Drupal 11.2 or later.
    /** @var \Drupal\key\Entity\Key|null $original */
    $original = method_exists($this, 'getOriginal') ? $this->getOriginal() : ($this->original ?? NULL);

    // If an original key exists.
    if ($original) {
      // If the original key's provider allows setting a key value and
      // the plugin ID is different from the one that was just saved with
      // the entity.
      if ($original->getKeyProvider() instanceof KeyProviderSettableValueInterface
        && $original->getKeyProvider()->getPluginId() != $this->getKeyProvider()->getPluginId()
      ) {
        // Allow the original key's provider to delete the key value.
        $original->deleteKeyValue();
      }
    }

    parent::postSave($storage, $update);
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    foreach ($entities as $key) {
      /** @var \Drupal\key\Entity\Key $key */
      $key->invalidateKeyValueCache();

      // Give the key provider plugin the opportunity to delete the key value.
      if ($key->getKeyProvider() instanceof KeyProviderSettableValueInterface) {
        $key->deleteKeyValue();
      }
    }

    parent::postDelete($storage, $entities);
  }

}
