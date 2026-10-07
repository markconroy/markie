<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;

/**
 * Provides functionality for AiTextFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "ai_text_prompt_constraint_rule",
 *   label = @Translation("AI text prompt constraint"),
 *   description = @Translation("AI text prompt constraint.")
 * )
 */
class AiTextConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'chat';
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
    return 'AiTextPrompt';
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
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Copied from core.
    $message = 'This value is not valid.';

    if ($this->configuration['prompt'] == '') {
      $this->configuration['prompt'] = 'You can only answer with XTRUE or XFALSE.
Take the following input and check if it mentions Drupal.
If it is answer XTRUE, if its not answer XFALSE. ';
    }

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Make sure the prompt ends in such a way that we can parse the output. eg: just respond with XTRUE if (condition) otherwise answer with XFALSE'),
      '#default_value' => $this->configuration['prompt'],
      '#required' => TRUE,
    ];

    $form['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => $this->getOperationType(),
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildModelDefaultValue(),
    ];

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
  }

}
