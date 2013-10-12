<?php

/**
 * @file
 * Contains a Views field handler to take care of displaying links to entities
 * as fields.
 */

class entityform_handler_link_field extends views_handler_field {
  function construct() {
    parent::construct();

    $this->additional_fields['entityform_id'] = 'entityform_id';
    $this->additional_fields['type'] = 'type';
  }

  function option_definition() {
    $options = parent::option_definition();

    $options['text'] = array(
      'default' => '',
      'translatable' => TRUE,
    );

    return $options;
  }

  function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);

    $form['text'] = array(
      '#type' => 'textfield',
      '#title' => t('Text to display'),
      '#default_value' => $this->options['text'],
    );
  }

  function query() {
    $this->ensure_my_table();
    $this->add_additional_fields();
  }

  function render($values) {
    $text = !empty($this->options['text']) ? $this->options['text'] : t('view');
    $entityform_id = $values->{$this->aliases['entityform_id']};

    return l($text, 'entityform/' . $entityform_id);
  }
  protected function create_dummyentityform($values) {
    $dummy_entityform = new stdClass();
    $dummy_entityform->type = $values->{$this->aliases['type']};
    if (isset($this->additional_fields['uid'])) {
      $dummy_entityform->uid = $values->{$this->aliases['uid']};
    }
    return $dummy_entityform;
  }
}
