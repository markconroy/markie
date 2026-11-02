<?php

namespace Drupal\ai_automators\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_automators\Attribute\AiAutomatorType;
use Drupal\ai_automators\PluginBaseClasses\ComplexTextChat;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\Traits\TextFormatAccessTrait;
use Drupal\ai_automators\Traits\RichTextImageDescriptionTrait;

/**
 * The rules for a text_long field.
 */
#[AiAutomatorType(
  id: 'llm_text_long',
  label: new TranslatableMarkup('LLM: Text'),
  field_rule: 'text_long',
  target: '',
)]
class LlmTextLong extends ComplexTextChat implements AiAutomatorTypeInterface {

  use RichTextImageDescriptionTrait;
  use TextFormatAccessTrait;

  /**
   * {@inheritDoc}
   */
  public $title = 'LLM: Text';

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = parent::extraAdvancedFormFields($entity, $fieldDefinition, $formState, $defaultValues);

    $form['automator_use_text_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Use text format'),
      '#description' => $this->t('If you want to use a specific text format, select it here. Otherwise a text format will be used based on user rights. If the user who saves the content may not use the selected format, the output is stored in the first format of the field they may use instead, or skipped if there is none. Queued automators run as that user too. Content saved without a logged-in user, for example from cron, runs as the fallback user from the AI Automators settings, or as anonymous.'),
      '#options' => $this->getGeneralHelper()->getTextFormatsOptions(),
      '#default_value' => $defaultValues['automator_use_text_format'] ?? NULL,
    ];

    return $this->addImageDescriptionConfigurationForm($form, $defaultValues);
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Get text format.
    $textFormat = !empty($automatorConfig['use_text_format'])
      ? $this->textFormatForCurrentUser($entity, $fieldDefinition, $automatorConfig['use_text_format'])
      : $this->getGeneralHelper()->calculateTextFormat($fieldDefinition);

    // Then set the value.
    $cleanedValues = [];
    foreach ($values as $value) {
      $cleanedValues[] = [
        'value' => $value,
        'format' => $textFormat,
      ];
    }
    $entity->set($fieldDefinition->getName(), $cleanedValues);
    $this->storeImageDescriptionsMetadata($entity, $automatorConfig);
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    if (!$this->currentUserCanUseConfiguredTextFormat($entity, $fieldDefinition, $automatorConfig)) {
      return [];
    }
    $this->initializeImageDescriptionMetadata();
    return parent::generate($entity, $fieldDefinition, $automatorConfig);
  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig, $delta);
    return $this->appendImageDescriptionsToTokens($entity, $automatorConfig, (int) $delta, $tokens);
  }

}
