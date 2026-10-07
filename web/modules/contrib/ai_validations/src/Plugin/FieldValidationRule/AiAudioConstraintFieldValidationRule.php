<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;

/**
 * Validates an audio file field using speech-to-text and a chat prompt.
 *
 * @FieldValidationRule(
 *   id = "ai_audio_constraint_rule",
 *   label = @Translation("AI audio constraint"),
 *   description = @Translation("Transcribes an audio file with a speech-to-text provider, then evaluates the transcript with a chat provider.")
 * )
 */
class AiAudioConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'speech_to_text';
  }

  /**
   * {@inheritdoc}
   */
  protected function getModelConfigKey(): string {
    return 'provider';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return 'AiAudioPrompt';
  }

  /**
   * {@inheritdoc}
   */
  public function isPropertyConstraint(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'prompt' => NULL,
      'message' => NULL,
      'chat_provider' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Speech-to-text provider'),
      '#description' => $this->t('The AI provider used to transcribe the audio file.'),
      '#operation_type' => 'speech_to_text',
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildModelDefaultValue(),
    ];

    $form['chat_provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Chat provider'),
      '#description' => $this->t('The AI provider used to evaluate the transcript against the prompt.'),
      '#operation_type' => 'chat',
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildProviderDefaultValue('chat_provider'),
    ];

    if (empty($this->configuration['prompt'])) {
      $this->configuration['prompt'] = 'You can only answer with XTRUE or XFALSE.
Listen to the following audio transcript and check if the content is appropriate.
If it is appropriate answer XTRUE, if it is not answer XFALSE.';
    }

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Make sure the prompt ends in such a way that we can parse the output. eg: just respond with XTRUE if (condition) otherwise answer with XFALSE'),
      '#default_value' => $this->configuration['prompt'],
      '#required' => TRUE,
    ];

    $message = 'This value is not valid.';
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['prompt'] = $form_state->getValue('prompt');
    $this->configuration['message'] = $form_state->getValue('message');

    $stored_provider = $this->extractStoredModel($form_state->getValue('provider'));
    $this->configuration['provider'] = $stored_provider;
    $form_state->setValue('provider', $stored_provider);

    $stored_chat = $this->extractStoredModel($form_state->getValue('chat_provider'));
    $this->configuration['chat_provider'] = $stored_chat;
    $form_state->setValue('chat_provider', $stored_chat);
  }

}
