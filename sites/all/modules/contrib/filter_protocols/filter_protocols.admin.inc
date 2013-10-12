<?php

/**
 * @file
 * Admin page callbacks for the filter_protocols module.
 */

function filter_protocols_settings_form() {
  $form['filter_allowed_protocols'] = array(
    '#type' => 'textfield',
    '#title' => t('Allowed protocols'),
    '#description' => t('A list of protocols that can be used.'),
    '#default_value' => implode(' ', variable_get('filter_allowed_protocols', array('ftp', 'http', 'https', 'irc', 'mailto', 'news', 'nntp', 'rtsp', 'sftp', 'ssh', 'tel', 'telnet', 'webcal'))),
    '#size' => 80,
  );

  return system_settings_form($form);
}

function filter_protocols_settings_form_validate($form, &$form_state) {
  $protocols = &$form_state['values']['filter_allowed_protocols'];
  $protocols = drupal_strtolower(trim($protocols));
  $protocols = preg_split('/[\s]+/', $protocols, 0, PREG_SPLIT_NO_EMPTY);
  $protocols = array_unique($protocols);
  foreach ($protocols as $protocol) {
    if (!preg_match('/^[a-z][\w]*$/', $protocol)) {
      form_set_error('filter_allowed_protocols', t('Invalid protocol %protocol.', array('%protocol' => $protocol)));
    }
  }
}
