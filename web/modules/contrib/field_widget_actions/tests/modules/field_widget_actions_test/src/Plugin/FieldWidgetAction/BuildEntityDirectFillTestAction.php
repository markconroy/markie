<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * Direct-fill test action that builds the entity in its AJAX callback.
 *
 * Mirrors the ai_automators direct-fill path (AutomatorBaseAction::
 * populateAutomatorValues() -> static::buildEntity()): clicking the button
 * builds the host entity from the submitted form values. Used to verify that
 * building the entity does not crash when a multi-value field is left empty.
 */
#[FieldWidgetAction(
  id: 'build_entity_direct_fill',
  label: new TranslatableMarkup('Build entity (direct fill)'),
  widget_types: [
    'options_select',
    'options_buttons',
  ],
  field_types: [
    'entity_reference',
    'list_string',
    'list_integer',
    'list_float',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class BuildEntityDirectFillTestAction extends FieldWidgetActionBase {

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'buildEntityAndReport';
  }

  /**
   * AJAX callback that builds the entity, then reports success.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function buildEntityAndReport(array &$form, FormStateInterface $form_state): AjaxResponse {
    // Recreate the failing condition deterministically: some multi-value
    // widgets (e.g. the contrib chosen_select) submit a literal NULL at the
    // field's value path for an empty selection, where core widgets submit an
    // empty array. Force NULL so the test exercises buildEntity()'s tolerance
    // of it regardless of the widget in use.
    $field_name = $this->getTargetElementFieldName($form, $form_state);
    if ($field_name) {
      $form_state->setValue($field_name, NULL);
    }

    // The build must not throw on the NULL value.
    $this->buildEntity($form, $form_state);
    $response = new AjaxResponse();
    $response->addCommand(new MessageCommand('Entity built without error.'));
    return $response;
  }

}
