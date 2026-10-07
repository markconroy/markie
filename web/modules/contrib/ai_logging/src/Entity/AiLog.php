<?php

namespace Drupal\ai_logging\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\ai_logging\AiLogInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines the AI Log entity.
 *
 * @ContentEntityType(
 *   id = "ai_log",
 *   label = @Translation("AI Log"),
 *   base_table = "ai_log",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "bundle",
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\ai_logging\AiLogAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\ai_logging\ViewBuilder\LogViewBuilder",
 *     "field_ui" = "Drupal\field_ui\Entity\EntityFormDisplay",
 *   },
 *   links = {
 *     "canonical" = "/admin/config/ai/logging/collection/{ai_log}",
 *     "add-page" = "/admin/config/ai/logging/collection/add",
 *     "add-form" = "/admin/config/ai/logging/collection/add/{ai_log_type}",
 *     "edit-form" = "/admin/config/ai/logging/collection/{ai_log}/edit",
 *     "delete-form" = "/admin/config/ai/logging/collection/{ai_log}/delete",
 *     "collection" = "/admin/config/ai/logging/collection",
 *   },
 *   admin_permission = "administer ai log",
 *   bundle_entity_type = "ai_log_type",
 *   field_ui_base_route = "entity.ai_log_type.edit_form",
 * )
 */
class AiLog extends ContentEntityBase implements AiLogInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields[$entity_type->getKey('bundle')]->setDisplayConfigurable('form', TRUE);
    $fields[$entity_type->getKey('bundle')]->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the ai log was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Long string for prompt.
    $fields['prompt'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Prompt'))
      ->setDescription(t('The prompt for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['operation_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Operation Type'))
      ->setDescription(t('The operation type.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['provider'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Provider'))
      ->setDescription(t('The provider.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['model'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Model'))
      ->setDescription(t('Model.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tags'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tags'))
      ->setDescription(t('The tags for the ai log.'))
      ->setCardinality(-1)
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['output_text'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Response'))
      ->setDescription(t('The output text for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['response_text'] = static::responseTextFieldDefinition();

    $fields['configuration'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Configuration'))
      ->setDescription(t('The configuration for the ai log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['extra_data'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Extra Data'))
      ->setDescription(t('Extra Data to log.'))
      ->setSettings([
        'default_value' => '',
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tokens_input'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Input Tokens'))
      ->setDescription(t('The number of input (prompt) tokens used for the ai log.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 10,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tokens_output'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Output Tokens'))
      ->setDescription(t('The number of output (completion) tokens used for the ai log.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 11,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 11,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tokens_total'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Total Tokens'))
      ->setDescription(t('The total number of tokens used for the ai log.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 12,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 12,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tokens_reasoning'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Reasoning Tokens'))
      ->setDescription(t('The number of reasoning tokens used for the ai log, if reported by the provider.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 13,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 13,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tokens_cached'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Cached Tokens'))
      ->setDescription(t('The number of cached tokens used for the ai log, if reported by the provider.'))
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => 14,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'number_integer',
        'weight' => 14,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Builds the field definition for the normalized reply text.
   *
   * Shared with ai_logging_update_10310() so that existing sites get exactly
   * the same field storage as a fresh install.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition
   *   The response text base field definition.
   */
  public static function responseTextFieldDefinition(): BaseFieldDefinition {
    return BaseFieldDefinition::create('string_long')
      ->setLabel(t('Reply'))
      ->setDescription(t('The text the model replied with, as normalized by the AI module. A structured (JSON schema) call stores its JSON answer here.'))
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->t('Ai Log ID @id', ['@id' => $this->id()]);
  }

}
