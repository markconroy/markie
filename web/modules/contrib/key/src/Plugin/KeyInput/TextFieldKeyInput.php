<?php

namespace Drupal\key\Plugin\KeyInput;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\key\Attribute\KeyInput;
use Drupal\key\KeyInterface;
use Drupal\key\Plugin\KeyInputBase;
use Drupal\key\Plugin\KeyPluginInterface;

/**
 * Defines a key input that provides a simple text field.
 */
#[KeyInput(
  id: 'text_field',
  label: new TranslatableMarkup('Text field'),
  description: new TranslatableMarkup('A simple text field.'),
)]
class TextFieldKeyInput extends KeyInputBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'key_value' => '',
      'base64_encoded' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $key_value_data = $form_state->get('key_value');

    $form_object = $form_state->getFormObject();
    assert($form_object instanceof EntityFormInterface);
    $key = $form_object->getEntity();
    assert($key instanceof KeyInterface);
    $key_provider = $key->getKeyProvider();
    assert($key_provider instanceof KeyPluginInterface);
    $key_type = $key->getKeyType();
    assert($key_type instanceof KeyPluginInterface);

    $form['key_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key value'),
      '#required' => $key_provider->getPluginDefinition()['key_value']['required'],
      '#maxlength' => 4096,
      '#default_value' => $key_value_data['current'],
      // Tell the browser not to autocomplete this field.
      '#attributes' => ['autocomplete' => 'off'],
    ];

    // If the key type stores multiple values, describe the expected format.
    $key_type_definition = $key_type->getPluginDefinition();
    if (!empty($key_type_definition['multivalue']['enabled'])) {
      $fields = array_keys($key_type_definition['multivalue']['fields'] ?? []);
      if ($fields) {
        $form['key_value']['#description'] = $this->t('Enter the key value as a JSON object with the following attributes: @fields. Example: @example', [
          '@fields' => implode(', ', $fields),
          '@example' => Json::encode(array_fill_keys($fields, '...')),
        ]);
      }
      else {
        $form['key_value']['#description'] = $this->t('Enter the key value as a JSON object.');
      }
    }

    // If this key input is for an encryption key.
    if ($key_type->getPluginDefinition()['group'] == 'encryption') {
      // Add an option to indicate that the value is Base64-encoded.
      $form['base64_encoded'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Base64-encoded'),
        '#description' => $this->t('Check this if the key value being submitted has been Base64-encoded.'),
        '#default_value' => $this->getConfiguration()['base64_encoded'],
      ];
    }

    return $form;
  }

}
