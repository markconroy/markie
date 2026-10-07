<?php

declare(strict_types=1);

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;

/**
 * Refinable test action whose modal form always fails validation.
 *
 * Used to verify that the Cancel action dismisses the modal even when the
 * modal form would otherwise fail validation — without trapping the user.
 */
#[FieldWidgetAction(
  id: 'refinable_validating_for_textfield',
  label: new TranslatableMarkup('Refinable validating for field'),
  widget_types: [
    'string_textfield',
    'string_textarea',
    'text_textfield',
    'text_textarea',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class RefinableValidatingTestAction extends RefinableTextsTestAction {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setErrorByName('content', $this->t('Validation always fails in this test action.'));
  }

}
