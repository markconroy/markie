<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\FieldValidationRule;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_validations\AiConstraintFieldValidationRuleBase;
use Drupal\image\Entity\ImageStyle;

/**
 * Provides functionality for AI Object Detection.
 *
 * @FieldValidationRule(
 *   id = "ai_object_detection_constraint_rule",
 *   label = @Translation("AI object detection constraint"),
 *   description = @Translation("Uses object detection AI to validate the field.")
 * )
 */
class AiObjectDetectionConstraintFieldValidationRule extends AiConstraintFieldValidationRuleBase {

  /**
   * {@inheritdoc}
   */
  protected function getOperationType(): string {
    return 'object_detection';
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraintName(): string {
    return 'AiObjectDetection';
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
      'model' => NULL,
      'keywords' => [],
      'finder' => 'exact',
      'minimum' => 0.8,
      'keywordFilter' => 'or',
      'ruleMode' => 'disapprove_when_found',
      'na' => 'skip',
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
      '#title' => $this->t('Object Detection Model'),
      '#description' => $this->t('Select the AI provider and model to use for object detection validation.'),
      '#operation_type' => $this->getOperationType(),
      '#advanced_config' => FALSE,
      '#default_provider_allowed' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->buildModelDefaultValue(),
    ];

    $keywords_default = '';
    if (!empty($this->configuration['keywords']) && is_array($this->configuration['keywords'])) {
      $keywords_default = implode("\n", $this->configuration['keywords']);
    }

    $form['keywords'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Keywords'),
      '#description' => $this->t('Enter one object label per line (or separated by commas).'),
      '#default_value' => $keywords_default,
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
      '#description' => $this->t('The minimum confidence level required for a detection to be considered a match.'),
      '#default_value' => $this->configuration['minimum'] ?? 0.8,
      '#required' => TRUE,
      '#min' => 0,
      '#max' => 1,
      '#step' => 0.001,
    ];

    $form['keyword_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Keyword Filter'),
      '#options' => [
        'or' => $this->t('Any keyword (OR)'),
        'and' => $this->t('All keywords (AND)'),
      ],
      '#default_value' => $this->configuration['keywordFilter'] ?? 'or',
    ];

    $form['rule_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Rule Mode'),
      '#options' => [
        'approve_when_found' => $this->t('Approve only when object(s) found'),
        'disapprove_when_found' => $this->t('Disapprove when object(s) found'),
      ],
      '#default_value' => $this->configuration['ruleMode'] ?? 'disapprove_when_found',
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

    // Store normalized string so field_validation persists it correctly.
    $stored_model = $this->extractStoredModel($form_state->getValue('model'));
    $this->configuration['model'] = $stored_model !== '' ? $stored_model : NULL;
    $form_state->setValue('model', $this->configuration['model']);

    $this->configuration['finder'] = $form_state->getValue('finder');
    $this->configuration['minimum'] = (float) $form_state->getValue('minimum');
    $this->configuration['keywordFilter'] = $form_state->getValue('keyword_filter');
    $this->configuration['ruleMode'] = $form_state->getValue('rule_mode');
    $this->configuration['na'] = $form_state->getValue('na');
    $this->configuration['message'] = $form_state->getValue('message');

    $raw_keywords = (string) $form_state->getValue('keywords');
    $parts = preg_split('/[\r\n,]+/', $raw_keywords) ?: [];
    $keywords = array_values(array_filter(array_map('trim', $parts), 'strlen'));
    $this->configuration['keywords'] = $keywords;

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
