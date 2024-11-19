<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\ai_assistant_api\AiAssistantInterface;

/**
 * Defines the ai assistant entity type.
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
 *     "pre_action_prompt",
 *     "preprompt_instructions",
 *     "system_role",
 *     "actions_enabled",
 *     "assistant_message",
 *     "error_message",
 *     "llm_provider",
 *     "llm_model",
 *     "llm_configuration",
 *   },
 * )
 */
final class AiAssistant extends ConfigEntityBase implements AiAssistantInterface {

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
   * Allow history.
   */
  protected string $allow_history;

  /**
   * The system role.
   */
  protected string $system_role;

  /**
   * The pre action prompt.
   */
  protected string $pre_action_prompt;

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
   * The error message.
   */
  protected string $error_message;

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

}
