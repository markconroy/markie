<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillCheckboxesOrRadiosCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetFormActionBase;

/**
 * Test action to fill checkboxes or radio buttons on an options_buttons widget.
 */
#[FieldWidgetAction(
  id: 'fill_checkboxes_or_radios_test_action',
  label: new TranslatableMarkup('Fill checkboxes or radios test action'),
  widget_types: [
    'options_buttons',
  ],
  field_types: [
    'entity_reference',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class FillCheckboxesOrRadiosTestAction extends FieldWidgetFormActionBase {

  /**
   * {@inheritdoc}
   */
  public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array {
    $form['confirm'] = [
      '#plain_text' => $this->t('Click to confirm insert.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse {
    $context_data = $form_state->get('field_widget_action_context_data');
    $field_name = $context_data['target_element_field_name'];
    // Select entity IDs 1 and 3. For single-value (radio) fields, only the
    // first value (1) is applied.
    $response->addCommand(new FillCheckboxesOrRadiosCommand(
      $field_name . '[widget]',
      [1, 3],
    ));
    return $response;
  }

}
