<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * Command-based action that returns a fill command with an empty payload.
 *
 * Models a plugin whose backend came back with nothing: the response is
 * well-formed and carries a fill command, but the command has no value. Used
 * to verify that the base class reports the empty outcome to the author
 * instead of silently redrawing an unchanged widget.
 */
#[FieldWidgetAction(
  id: 'empty_fill',
  label: new TranslatableMarkup('Fill with nothing'),
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
class EmptyFillTestAction extends FieldWidgetActionBase {

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'fillWithNothing';
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
   * AJAX callback returning a fill command with an empty value.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function fillWithNothing(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $target_element = $this->getTargetElement($form, $form_state);
    if (!empty($target_element['#name'])) {
      $response->addCommand(new FillSimpleFieldCommand('[name="' . $target_element['#name'] . '"]', ''));
    }
    return $response;
  }

}
