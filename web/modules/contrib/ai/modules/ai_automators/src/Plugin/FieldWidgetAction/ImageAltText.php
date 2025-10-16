<?php

namespace Drupal\ai_automators\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\Traits\ImageAltTextActionButtonTrait;

/**
 * The AltText action.
 */
#[FieldWidgetAction(
  id: 'automator_alt_text',
  label: new TranslatableMarkup('Automator Alt Text'),
  widget_types: ['image_image', 'image_focal_point'],
  field_types: ['image'],
  category: new TranslatableMarkup('AI Automators'),
)]
class ImageAltText extends AutomatorBaseAction {

  use ImageAltTextActionButtonTrait;

  /**
   * {@inheritdoc}
   */
  public string $formElementProperty = 'alt';

  /**
   * {@inheritdoc}
   */
  public bool $clearEntity = FALSE;

  /**
   * Ajax handler for Automators.
   */
  public function aiAutomatorsAjax(array &$form, FormStateInterface $form_state) {
    // Get the triggering element, as it contains the settings.
    $triggering_element = $form_state->getTriggeringElement();
    $array_parents = $triggering_element['#array_parents'];
    array_pop($array_parents);
    $array_parents[] = $this->formElementProperty;
    $key = $array_parents[2] ?? 0;
    $form_key = $array_parents[0];
    return $this->populateAutomatorValues($form, $form_state, $form_key, $key);
  }

}
