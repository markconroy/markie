<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;
use Drupal\ai_validations\AiContextAwareFieldValidationRuleInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Provides functionality for EmailFieldValidationRule.
 *
 * @FieldValidationRule(
 *   id = "ai_image_constraint_rule",
 *   label = @Translation("Ai image constraint"),
 *   description = @Translation("Ai image constraint.")
 * )
 */
class AiImageConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase implements AiContextAwareFieldValidationRuleInterface {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'chat_with_image_vision';
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
    return 'AiImagePrompt';
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
      'warningMessage' => NULL,
      'imageStyle' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    if ($this->configuration['prompt'] == '') {
      $this->configuration['prompt'] = 'You can only answer with XTRUE or XFALSE.
Take the following image and check if the Drupal logo is in the image.
If it is answer XTRUE, if its not answer XFALSE. ';
    }

    $form['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#description' => $this->t('Make sure the prompt ends in such a way that we can parse the output. With no warning message set below: just respond with XTRUE if (condition) otherwise answer with XFALSE. With a warning message set: respond with exactly one of XSAFE, XWARN or XBLOCK.'),
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

    $message = 'This value is not valid.';
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
      '#maxlength' => 255,
    ];

    $form['warning_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Warning message'),
      '#description' => $this->t('Optional. Leave empty to keep the XTRUE/XFALSE protocol, where a failing answer blocks the content. When set, the prompt must instruct the AI to answer with exactly one of XSAFE, XWARN or XBLOCK: XBLOCK blocks the content with the message above, while XWARN accepts the content and shows this warning to the user.'),
      '#default_value' => $this->configuration['warningMessage'] ?? '',
      '#maxlength' => 255,
    ];

    $image_styles = ['' => $this->t('— Original (no style) —')];
    foreach (ImageStyle::loadMultiple() as $style) {
      $image_styles[$style->id()] = $style->label();
    }
    $form['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style'),
      '#description' => $this->t('Optional. Select a downscale image style to reduce tokens sent to the AI provider. Leave empty to send the original.'),
      '#options' => $image_styles,
      '#default_value' => $this->configuration['imageStyle'] ?? '',
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

    $warning = $form_state->getValue('warning_message');
    $this->configuration['warningMessage'] = ($warning === '' || $warning === NULL) ? NULL : (string) $warning;

    $stored_provider = $this->extractStoredModel($form_state->getValue('provider'));
    $this->configuration['provider'] = $stored_provider;
    $form_state->setValue('provider', $stored_provider);

    $style_id = $form_state->getValue('image_style');
    $this->configuration['imageStyle'] = ($style_id === '' || $style_id === NULL) ? NULL : (string) $style_id;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $deps = parent::calculateDependencies();
    if (!empty($this->configuration['imageStyle'])) {
      $deps['config'][] = 'image.style.' . $this->configuration['imageStyle'];
    }
    return $deps;
  }

}
