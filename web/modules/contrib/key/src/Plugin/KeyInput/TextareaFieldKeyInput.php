<?php

namespace Drupal\key\Plugin\KeyInput;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Attribute\KeyInput;

/**
 * Defines a key input that provides a simple text field.
 */
#[KeyInput(
  id: 'textarea_field',
  label: new TranslatableMarkup('Textarea field'),
  description: new TranslatableMarkup('A simple textarea field.'),
)]
class TextareaFieldKeyInput extends TextFieldKeyInput {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['key_value']['#type'] = 'textarea';
    // Multi-line values such as certificates and PEM-encoded keys can exceed
    // the single-line text field limit, so a textarea is not length-limited.
    unset($form['key_value']['#maxlength']);

    return $form;
  }

}
