<?php

/**
 * @file
 * Contains a Views field handler to take care of displaying edit links
 * as fields
 */


class entityform_handler_edit_link_field extends entityform_handler_link_field {
  function construct() {
    parent::construct();
    $this->additional_fields['uid'] = 'uid';
  }


  function render($values) {
    if (!entityform_access('edit', $this->create_dummyentityform($values))) {
      return;
    }

    $text = !empty($this->options['text']) ? $this->options['text'] : t('edit');
    $entityform_id = $values->{$this->aliases['entityform_id']};

    return l($text, 'entityform/' . $entityform_id . '/edit');
  }
}
