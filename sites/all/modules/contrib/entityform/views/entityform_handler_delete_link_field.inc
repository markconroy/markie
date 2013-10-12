<?php

/**
 * @file
 * Contains a Views field handler to take care of displaying deletes links
 * as fields
 */


class entityform_handler_delete_link_field extends entityform_handler_link_field {
  function construct() {
    parent::construct();
    $this->additional_fields['uid'] = 'uid';
  }


  function render($values) {
    if (!entityform_access('delete', $this->create_dummyentityform($values))) {
      return;
    }

    $text = !empty($this->options['text']) ? $this->options['text'] : t('delete');
    $entityform_id = $values->{$this->aliases['entityform_id']};

    return l($text, 'entityform/' . $entityform_id . '/delete');
  }
}
