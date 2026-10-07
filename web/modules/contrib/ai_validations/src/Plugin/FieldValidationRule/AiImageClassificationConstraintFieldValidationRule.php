<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;
use Drupal\image\Entity\ImageStyle;

/**
 * Provides functionality for AI Image Classification.
 *
 * @FieldValidationRule(
 *   id = "ai_image_classification constraint_rule",
 *   label = @Translation("AI image classification constraint"),
 *   description = @Translation("Uses Image classification AI to validate the field.")
 * )
 */
class AiImageClassificationConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'image_classification';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return 'AiImageClassification';
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
      'tag' => NULL,
      'finder' => 'exact',
      'model' => NULL,
      'minimum' => 0.8,
      'message' => NULL,
      'imageStyle' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['model'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Classification Model'),
      '#operation_type' => $this->getOperationType(),
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildModelDefaultValue(),
    ];

    $form['tag'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Classification Tag'),
      '#description' => $this->t('The tag that the image should be classified as.'),
      '#default_value' => $this->configuration['tag'] ?? '',
      '#required' => TRUE,
    ];

    $form['finder'] = [
      '#type' => 'select',
      '#title' => $this->t('Finder'),
      '#options' => [
        'exact' => $this->t('Exact'),
        'contains' => $this->t('Contains'),
        'substring' => $this->t('Contains (Case-Insensitive)'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->configuration['finder'] ?? 'exact',
    ];

    $form['minimum'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum Confidence'),
      '#description' => $this->t('The minimum confidence level required for the classification to trigger.'),
      '#default_value' => $this->configuration['minimum'] ?? 0.8,
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.001,
    ];

    $form['na'] = [
      '#type' => 'select',
      '#title' => $this->t('If model is not available'),
      '#options' => [
        'skip' => $this->t('Skip validation'),
        'fail' => $this->t('Fail validation'),
      ],
      '#default_value' => $this->configuration['na'] ?? 'skip',
      '#description' => $this->t('What to do if the model is not available.'),
    ];

    $message = 'This value is not valid.';
    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'] ?? $message,
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

    $this->configuration['message'] = $form_state->getValue('message');
    $this->configuration['tag'] = $form_state->getValue('tag');
    $this->configuration['finder'] = $form_state->getValue('finder');
    $this->configuration['minimum'] = $form_state->getValue('minimum');
    $this->configuration['na'] = $form_state->getValue('na');

    $stored_model = $this->extractStoredModel($form_state->getValue('model'));
    $this->configuration['model'] = $stored_model;
    $form_state->setValue('model', $stored_model);

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
