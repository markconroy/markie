<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test form for AI Provider Configuration element.
 */
final class AiProviderConfigurationTestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_provider_configuration_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $operation_type = 'chat', bool $advanced_config = TRUE, bool $default_provider_allowed = TRUE): array {
    $form['provider_config'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('AI Provider Configuration'),
      '#description' => $this->t('Select an AI provider and model.'),
      '#operation_type' => $operation_type,
      '#advanced_config' => $advanced_config,
      '#default_provider_allowed' => $default_provider_allowed,
      '#required' => FALSE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $form['result'] = [
      '#type' => 'markup',
      '#markup' => '<div id="form-result"></div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $value = $form_state->getValue('provider_config');
    $this->messenger()->addStatus($this->t('Form submitted with value: @value', [
      '@value' => print_r($value, TRUE),
    ]));
  }

}
