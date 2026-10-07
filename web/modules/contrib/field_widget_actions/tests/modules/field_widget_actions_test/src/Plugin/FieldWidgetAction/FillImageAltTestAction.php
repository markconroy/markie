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
 * Command-based action targeting the alternative text of an image widget.
 *
 * The image widget element is itself a managed_file input; the alternative
 * text is a text field child of it. Overriding FORM_ELEMENT_PROPERTY must make
 * the lookup return that child rather than the managed_file element.
 */
#[FieldWidgetAction(
  id: 'fill_image_alt_test_action',
  label: new TranslatableMarkup('Fill image alternative text'),
  widget_types: [
    'image_image',
  ],
  field_types: [
    'image',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class FillImageAltTestAction extends FieldWidgetActionBase {

  /**
   * The alternative text property, not the file reference.
   */
  const FORM_ELEMENT_PROPERTY = 'alt';

  /**
   * The alternative text written by the action.
   */
  const FILL_VALUE = 'Alternative text from the action';

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'fillAlt';
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
   * AJAX callback filling the alternative text input.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   A response carrying one fill command, or none if no target was found.
   */
  public function fillAlt(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $target = $this->getTargetElement($form, $form_state);
    if (!empty($target['#name'])) {
      $response->addCommand(new FillSimpleFieldCommand('[name="' . $target['#name'] . '"]', static::FILL_VALUE));
    }
    return $response;
  }

}
