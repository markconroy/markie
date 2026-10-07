<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillCheckboxesOrRadiosCommand;
use Drupal\field_widget_actions\Ajax\FillSelectCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * Command-based action that fills whatever element the lookup resolves to.
 *
 * Unlike the other test actions, this one derives its selector and its fill
 * command entirely from the element getTargetElement() returns, so it fails
 * loudly (no command at all) whenever the lookup comes back empty. It is
 * deliberately allowed on every widget shape the lookup has to handle, except
 * the boolean checkbox that other tests rely on having no available action.
 */
#[FieldWidgetAction(
  id: 'resolve_target_test_action',
  label: new TranslatableMarkup('Fill the resolved target'),
  widget_types: [
    'datetime_default',
    'entity_reference_autocomplete',
    'entity_reference_autocomplete_tags',
    'image_image',
    'link_default',
    'options_buttons',
    'options_select',
    'string_textfield',
    'tagify_select_widget',
    'text_textarea_with_summary',
  ],
  field_types: [
    'datetime',
    'entity_reference',
    'image',
    'link',
    'list_string',
    'string',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class ResolveTargetTestAction extends FieldWidgetActionBase {

  /**
   * The value written into plain inputs.
   */
  const FILL_VALUE = 'Filled from the resolved target';

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'fillFromTarget';
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(): array {
    return [
      'field_widget_actions/commands',
    ];
  }

  /**
   * AJAX callback filling the resolved target element.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response carrying one fill command, or none if no target was found.
   */
  public function fillFromTarget(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $target = $this->getTargetElement($form, $form_state);
    if (empty($target['#name'])) {
      return $response;
    }
    // A #multiple select renders its name with a trailing '[]', which the
    // processed element records in #attributes; plain inputs use #name.
    $selector = '[name="' . ($target['#attributes']['name'] ?? $target['#name']) . '"]';
    switch ($target['#type'] ?? '') {
      case 'select':
        $response->addCommand(new FillSelectCommand($selector, $this->firstOptionKeys($target)));
        break;

      case 'checkboxes':
      case 'radios':
        $response->addCommand(new FillCheckboxesOrRadiosCommand($target['#name'], $this->firstOptionKeys($target)));
        break;

      default:
        $response->addCommand(new FillSimpleFieldCommand($selector, static::FILL_VALUE));
    }
    return $response;
  }

  /**
   * Returns the first two real option keys of an options element.
   *
   * @param array $element
   *   A select, checkboxes or radios element.
   *
   * @return array
   *   Up to two option keys, skipping the '_none' placeholder and flattening
   *   option groups.
   */
  protected function firstOptionKeys(array $element): array {
    $keys = [];
    foreach ($element['#options'] ?? [] as $key => $option) {
      if (is_array($option)) {
        $keys = array_merge($keys, array_keys($option));
      }
      elseif ($key !== '_none') {
        $keys[] = $key;
      }
    }
    return array_slice($keys, 0, 2);
  }

}
