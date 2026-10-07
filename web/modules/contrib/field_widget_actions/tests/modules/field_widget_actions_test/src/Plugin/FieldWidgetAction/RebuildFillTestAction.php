<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * Rebuild-based action that writes to user input and returns a render array.
 *
 * Models the AI Automators shape (AutomatorBaseAction): the value is written
 * into user input during the submit phase and the AJAX callback returns the
 * field's form subtree rather than an AjaxResponse, so nothing in the return
 * value describes the outcome. Such a plugin reports what it produced through
 * reportProducedValue() / reportNoValueProduced().
 *
 * The 'produce_value' setting selects which outcome to model, and 'silent'
 * suppresses reporting altogether, so a single plugin covers the populated,
 * the empty and the non-reporting case.
 */
#[FieldWidgetAction(
  id: 'rebuild_fill',
  label: new TranslatableMarkup('Rebuild fill'),
  widget_types: [
    'string_textfield',
    'string_textarea',
    'text_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class RebuildFillTestAction extends FieldWidgetActionBase {

  /**
   * The value written when the action is configured to produce one.
   */
  const GENERATED_VALUE = 'Value produced by the rebuild action.';

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'produce_value' => TRUE,
      'silent' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, $action_id = NULL) {
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    $element['produce_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Produce a value'),
      '#default_value' => $this->configuration['produce_value'] ?? TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function actionButton(array &$form, FormStateInterface $form_state, array $context = []) {
    parent::actionButton($form, $form_state, $context);
    $fieldName = $context['items']->getFieldDefinition()->getName();
    $widgetId = $this->getActionButtonWidgetId($fieldName, $context);
    // Populate during the submit phase, exactly as AutomatorBaseAction does,
    // so the value is in user input before the form rebuilds.
    $form[$widgetId]['#submit'][] = [$this, 'writeValueSubmit'];
    $form[$widgetId]['#executes_submit_callback'] = TRUE;
  }

  /**
   * Submit handler writing the generated value into user input.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function writeValueSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $field_name = $triggering_element['#field_widget_action_field_name'] ?? NULL;
    // Mirror AutomatorBaseAction::runAutomatorSubmit(): bail unless the field
    // is a direct child of $form, since the rebuild below assumes that shape.
    if (!$field_name || !isset($form[$field_name])) {
      return;
    }
    // A plugin that opts out of reporting entirely, to pin that the base class
    // stays silent rather than guessing.
    $reports = empty($this->configuration['silent']);
    // What the field held before this action ran, as AutomatorBaseAction reads
    // it off the entity: a value already in the field is not something this
    // action produced.
    $entity = $this->buildEntity($form, $form_state);
    $before = $entity ? $entity->get($field_name)->getValue() : [];
    $produced = FALSE;
    if (!empty($this->configuration['produce_value'])) {
      $input = $form_state->getUserInput();
      $input[$field_name][0][static::FORM_ELEMENT_PROPERTY] = static::GENERATED_VALUE;
      $form_state->setUserInput($input);
      $form_state->setValue($field_name, [0 => [static::FORM_ELEMENT_PROPERTY => static::GENERATED_VALUE]]);
      // Read the result back off the entity the same way $before was read, so
      // the two sides of the comparison have the same shape.
      $after = $this->buildEntity($form, $form_state);
      $produced = $after
        ? $this->fieldValuesDiffer($before, $after->get($field_name)->getValue())
        : FALSE;
    }
    if ($reports) {
      $produced
        ? $this->reportProducedValue($form_state, $field_name)
        : $this->reportNoValueProduced($form_state, $field_name);
    }
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'returnWidget';
  }

  /**
   * AJAX callback returning the rebuilt field subtree.
   *
   * @param array $form
   *   The form array, already rebuilt.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The field widget subtree.
   */
  public function returnWidget(array &$form, FormStateInterface $form_state): array {
    $field_name = $this->getTargetElementFieldName($form, $form_state);
    return $form[$field_name] ?? [];
  }

}
