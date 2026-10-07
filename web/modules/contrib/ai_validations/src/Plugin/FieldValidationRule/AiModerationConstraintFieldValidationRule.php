<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;

/**
 * Provides an AI moderation field validation rule.
 *
 * @FieldValidationRule(
 *   id = "ai_moderation_constraint_rule",
 *   label = @Translation("AI moderation constraint"),
 *   description = @Translation("Uses the AI moderation API to detect and block harmful or inappropriate content.")
 * )
 */
class AiModerationConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'moderation';
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
    return 'AiModeration';
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
      'provider' => NULL,
      'categories' => '',
      'threshold' => 0.5,
      'message' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Provider'),
      '#operation_type' => $this->getOperationType(),
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildModelDefaultValue(),
    ];

    $form['categories'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Categories'),
      '#description' => $this->t('Comma-separated list of moderation categories to watch (e.g. <em>hate,violence,sexual</em>). Leave blank to flag content in any category.'),
      '#default_value' => $this->configuration['categories'] ?? '',
      '#maxlength' => 255,
    ];

    $form['threshold'] = [
      '#type' => 'number',
      '#title' => $this->t('Confidence threshold'),
      '#description' => $this->t('Minimum confidence score (0–1) for a watched category to trigger a violation. Only applies when specific categories are configured above. Defaults to 0.5.'),
      '#default_value' => $this->configuration['threshold'] ?? 0.5,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.01,
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? 'This value is not valid.',
      '#maxlength' => 255,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['categories'] = $form_state->getValue('categories');
    $this->configuration['threshold'] = (float) $form_state->getValue('threshold');
    $this->configuration['message'] = $form_state->getValue('message');

    $stored_provider = $this->extractStoredModel($form_state->getValue('provider'));
    $this->configuration['provider'] = $stored_provider;
    $form_state->setValue('provider', $stored_provider);
  }

}
