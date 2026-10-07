<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Entity;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;
use Drupal\ai_assistant_api\AiAssistantInterface;

/**
 * Defines the AI assistant entity type.
 *
 * @ConfigEntityType(
 *   id = "ai_assistant",
 *   label = @Translation("AI Assistant"),
 *   label_collection = @Translation("AI Assistants"),
 *   label_singular = @Translation("AI Assistant"),
 *   label_plural = @Translation("AI Assistants"),
 *   label_count = @PluralTranslation(
 *     singular = "@count AI assistant",
 *     plural = "@count AI Assistants",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\ai_assistant_api\AiAssistantListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "edit" = "Drupal\ai_assistant_api\Form\AiAssistantForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "ai_assistant",
 *   admin_permission = "administer ai_assistant",
 *   links = {
 *     "collection" = "/admin/config/ai/ai-assistant",
 *     "add-form" = "/admin/config/ai/ai-assistant/add",
 *     "edit-form" = "//admin/config/ai/ai-assistant/{ai_assistant}",
 *     "delete-form" = "/admin/config/ai/ai-assistant/{ai_assistant}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "allow_history",
 *     "chat_memory_settings",
 *     "pre_action_prompt",
 *     "system_prompt",
 *     "instructions",
 *     "actions_enabled",
 *     "error_message",
 *     "specific_error_messages",
 *     "llm_provider",
 *     "llm_model",
 *     "llm_configuration",
 *     "roles",
 *     "use_function_calling",
 *     "ai_agent",
 *   },
 * )
 */
final class AiAssistant extends ConfigEntityBase implements AiAssistantInterface, EntityWithPluginCollectionInterface {

  /**
   * Chat memory plugin IDs, keyed by the allow_history value they replaced.
   */
  const LEGACY_CHAT_MEMORY_PLUGINS = [
    'session_one_thread' => 'private_tempstore',
    'session' => 'private_tempstore_pool',
    // 1.4.x offered "none" alongside the two session options. It has no chat
    // memory plugin, so it converts to no plugin at all - which is what
    // ai_assistant_api_post_update_convert_allow_history() did with any value
    // it did not recognize.
    'none' => '',
  ];

  /**
   * The thread expiry given to chat memory converted from allow_history.
   */
  const LEGACY_CHAT_MEMORY_EXPIRY = 604800;

  /**
   * The example ID.
   */
  protected string $id;

  /**
   * The example label.
   */
  protected string $label;

  /**
   * The example description.
   */
  protected string $description;

  /**
   * The chat memory plugin ID, or an empty string for no chat memory.
   */
  protected string $allow_history;

  /**
   * The chat memory plugin settings.
   */
  protected array $chat_memory_settings = [];

  /**
   * The system role.
   */
  protected string $system_role;

  /**
   * The pre action prompt.
   */
  protected ?string $pre_action_prompt;

  /**
   * The system prompt.
   *
   * @var string
   */
  protected ?string $system_prompt;

  /**
   * The instructions for the LLM.
   */
  protected ?string $instructions;

  /**
   * The instructions for the pre action prompt.
   */
  protected string $preprompt_instructions;

  /**
   * The actions enabled and their config.
   */
  protected array $actions_enabled = [];

  /**
   * The assistant message.
   */
  protected string $assistant_message;

  /**
   * The generic error message.
   */
  protected string $error_message;

  /**
   * The specific error message overrides.
   */
  protected ?array $specific_error_messages;

  /**
   * The LLM provider.
   */
  protected string $llm_provider;

  /**
   * The LLM model.
   */
  protected string $llm_model;

  /**
   * The LLM configuration.
   */
  protected array $llm_configuration;

  /**
   * The roles that can run this assistant.
   */
  protected array $roles = [];

  /**
   * Use function calling.
   */
  protected ?bool $use_function_calling = FALSE;

  /**
   * An AI Agent.
   */
  protected ?string $ai_agent = NULL;

  /**
   * The chat memory plugin collection.
   *
   * Not part of config_export. ConfigEntityBase::__sleep() removes it from the
   * serialized payload, which is what keeps the plugin instance — and any
   * service it holds — out of the form cache.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   */
  protected ?DefaultSingleLazyPluginCollection $chatMemoryPluginCollection = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct(static::upgradeLegacyChatMemorySettings($values), $entity_type);
  }

  /**
   * Rebuilds the chat memory settings from the pre-ChatMemory ones.
   *
   * Assistants configured before the ChatMemory plugins existed stored an
   * enum in allow_history and the number of messages to keep in
   * history_context_length. Those are migrated by
   * ai_assistant_api_post_update_convert_allow_history(), but config that was
   * exported before that update ran - or that is imported, or installed by a
   * recipe, afterwards - still arrives in the old shape, where allow_history
   * matches no chat memory plugin and the assistant would silently end up
   * with no chat memory at all. Such an assistant gets the chat memory plugin
   * that replaced its setting, configured from the settings it already has.
   * The legacy setting is dropped here, so the assistant is stored in the new
   * shape the next time it is saved.
   *
   * @param array $values
   *   The assistant values, possibly in the legacy shape.
   *
   * @return array
   *   The assistant values, in the ChatMemory shape.
   */
  protected static function upgradeLegacyChatMemorySettings(array $values): array {
    $max_messages = $values['history_context_length'] ?? NULL;
    unset($values['history_context_length']);

    $plugin_id = static::LEGACY_CHAT_MEMORY_PLUGINS[$values['allow_history'] ?? ''] ?? NULL;
    if ($plugin_id === NULL) {
      return $values;
    }

    $values['allow_history'] = $plugin_id;
    // "none" converts to no plugin at all, so there are no plugin settings to
    // carry over to it.
    if ($plugin_id === '') {
      $values['chat_memory_settings'] = [];
      return $values;
    }
    // Anything already set on the plugin wins over the legacy setting.
    $values['chat_memory_settings'] = ($values['chat_memory_settings'] ?? []) + [
      'expiry' => static::LEGACY_CHAT_MEMORY_EXPIRY,
    ];
    if ($max_messages !== NULL && !isset($values['chat_memory_settings']['max_messages'])) {
      $values['chat_memory_settings']['max_messages'] = (int) $max_messages;
    }

    return $values;
  }

  /**
   * Encapsulates the creation of the chat memory plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   *   The plugin collection, or NULL when no chat memory plugin is selected.
   */
  protected function getChatMemoryPluginCollection(): ?DefaultSingleLazyPluginCollection {
    if (empty($this->allow_history)) {
      return NULL;
    }

    if ($this->chatMemoryPluginCollection === NULL) {
      try {
        $this->chatMemoryPluginCollection = new DefaultSingleLazyPluginCollection(
          \Drupal::service('plugin.manager.ai.chat_memory'),
          $this->allow_history,
          $this->chat_memory_settings
        );
      }
      catch (PluginException) {
        // The configured plugin is gone: its module was uninstalled or the ID
        // was renamed. Behave as if no chat memory plugin is selected.
        // ConfigEntityBase calls getPluginCollections() from set(), __sleep()
        // and calculateDependencies(), so letting this escape would fatal on
        // every one of them and take the stored settings down with it.
        return NULL;
      }
    }

    return $this->chatMemoryPluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections(): array {
    $collection = $this->getChatMemoryPluginCollection();

    return $collection === NULL ? [] : ['chat_memory_settings' => $collection];
  }

  /**
   * Returns the chat memory plugin instance.
   *
   * @return \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface|null
   *   The chat memory plugin instance, or NULL if none is selected.
   */
  public function getChatMemory(): ?ChatMemoryInterface {
    $collection = $this->getChatMemoryPluginCollection();
    if ($collection === NULL) {
      return NULL;
    }

    try {
      // Passing the current plugin ID rather than relying on the collection's
      // own instance ID means a chat memory plugin that was swapped out by
      // self::set() is instantiated fresh instead of handing back the
      // previously selected one.
      $plugin = $collection->get($this->allow_history);
    }
    catch (PluginException) {
      return NULL;
    }

    return $plugin instanceof ChatMemoryInterface ? $plugin : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function set($property_name, $value) {
    // The assistant form writes the newly selected plugin ID onto the entity
    // on every AJAX rebuild. Drop the collection so it is rebuilt for the new
    // selection, otherwise the settings subform stays on the old plugin.
    // Both assignments happen before the parent call because
    // ConfigEntityBase::set() itself calls getPluginCollections(), which
    // would otherwise rebuild the collection on the plugin ID we are
    // replacing.
    if ($property_name === 'allow_history' && $value !== ($this->allow_history ?? NULL)) {
      $this->chatMemoryPluginCollection = NULL;
      $this->allow_history = $value;
    }

    return parent::set($property_name, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $properties = parent::toArray();
    // chat_memory_settings only has a schema for a non-empty allow_history
    // (it is typed dynamically off it, see ai_assistant_api.schema.yml): a
    // dynamic type keyed off an empty string does not resolve to any
    // defined schema. Omit the key entirely when no chat memory plugin is
    // selected, rather than exporting a value with no schema to validate
    // it against.
    if (empty($this->allow_history)) {
      unset($properties['chat_memory_settings']);
    }
    return $properties;
  }

}
