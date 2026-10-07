<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\Ajax\FillSelectCommand;
use Drupal\field_widget_actions\FieldWidgetFormActionBase;

/**
 * Test action to fill an entity reference.
 */
#[FieldWidgetAction(
  id: 'fill_entity_reference_test_action',
  label: new TranslatableMarkup('Fill entity reference test action'),
  widget_types: [
    'entity_reference_autocomplete_tags',
    'tagify_select_widget',
    'options_select',
  ],
  field_types: [
    'entity_reference',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class FillEntityReferenceTestAction extends FieldWidgetFormActionBase {

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
    $target_type = $context_data['target_element_field_settings']['target_type'];
    $widget_type = $context_data['target_element_widget_settings']['type'];
    $storage = $this->entityTypeManager->getStorage($target_type);

    // The selector comes from the element the base class resolved when the
    // modal was opened. Its name differs per widget and per action mode (a
    // per-item select is wrapped and named 'field[widget][]', a whole-field
    // one is 'field[]'), so nothing is hardcoded here: an empty target means
    // no fill command at all.
    $target_element = $context_data['target_element'] ?? [];
    $name = $target_element['#attributes']['name'] ?? $target_element['#name'] ?? '';
    if ($name === '') {
      return $response;
    }
    $selector = '[name="' . $name . '"]';

    // Select terms 1 and 3.
    $target_entity_ids = [1, 3];

    switch ($widget_type) {
      case 'entity_reference_autocomplete_tags':
        // Load the selected entities and format them for the editor.
        $formatted = [];
        $targets = $storage->loadMultiple($target_entity_ids);
        foreach ($targets as $target) {
          $formatted[] = sprintf('%s (%s)', $target->label(), $target->id());
        }
        $value = implode(', ', $formatted);
        $response->addCommand(new FillSimpleFieldCommand($selector, $value));
        break;

      case 'options_select':
      case 'tagify_select_widget':
        // The select list has the entity IDs as values, we can pass them
        // directly to the fill select command.
        $response->addCommand(new FillSelectCommand($selector, $target_entity_ids));
        break;
    }
    return $response;
  }

}
