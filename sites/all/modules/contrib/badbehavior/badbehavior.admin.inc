<?php

/**
 * @file
 * Admin page callbacks for the badbehavior module.
 */

/**
 * Builds the log table seen in the /admin/reports/badbehavior/ page.
 *
 * @return string
 * The actual table in HTML format
 */
function badbehavior_overview() {
  if (!badbehavior_load_includes(array('responses'))) {
    return 'Bad Behavior is not installed correctly. See the README.txt for installation details.';
  }
  $header = array(
    array('data' => t('Response')),
    array('data' => t('Reason')),
    array('data' => t('Date'), 'field' => 'b.date', 'sort' => 'desc'),
    array('data' => t('IP'), 'field' => 'b.ip'),
    array('data' => t('Agent'), 'field' => 'b.user_agent', 'colspan' => 2)
  );
  $logtype = variable_get('badbehavior_logging', 1);
  if ($logtype == 'verbose') {
    $sql = db_select('bad_behavior_log', 'b')
      ->fields('b')
      ->extend('PagerDefault')
        ->limit(20)
      ->extend('TableSort')
        ->orderByHeader($header)
      ->execute();
  }
  elseif ($logtype == '1') {
    $sql = db_select('bad_behavior_log', 'b')
      ->fields('b')
      ->extend('PagerDefault')
        ->limit(50)
      ->extend('TableSort')
        ->orderByHeader($header)
      ->condition('b.key', '00000000', '<>')
      ->execute();
  }
  $rows = array();
  if (isset($sql)) {
    foreach ($sql as $record) {
      $response = bb2_get_response($record->key);
      $record->localdate = bb2_convertdate($record->date);
      $rows[] = array(
        'data' => array(
          $response['response'],
          $response['log'],
          $record->localdate,
          $record->ip,
          $record->user_agent,
            l(t('details'), "admin/reports/badbehavior/event/$record->id")  // FIX ME
        )
      );
    }
  }
  $output = theme('table',
    array(
      'header' => $header,
      'rows' => $rows,
      'caption' => '',
      'empty' => 'No log messages available',
    )
  );
  $output .= theme('pager',
    array(
      'tags' => array()
    )
  );
  return $output;
}

/**
 * Builds the "Details" table displaying data about an individual log entry
 *
 * @param string $id
 *
 * @return string
 * The table in HTML format
 */
function badbehavior_event($id = NULL) {
  if (!badbehavior_load_includes(array('responses'))) {
    return 'Bad Behavior is not installed correctly. See the README.txt for installation details.';
  }
  $output = '';
  $result = db_select('bad_behavior_log', 'b')
    ->fields('b')
    ->condition('id', $id, '=')
    ->execute();
  $rows = array();
  foreach ($result as $record) {
    $response = bb2_get_response($record->key);
    $record->localdate = bb2_convertdate($record->date);
    $output .= '<table border="1" cellpadding="2" cellspacing="2">';
    $output .= ' <tr><th>' . t('IP Addr') . '</th><td>';
    if ($record->ip) {
      $output .= $record->ip . '</td></tr>'; }
    else {
      $output .= '<span style="color:red">Possible Proxy Settings Error:</span> No IP address reported</td></tr>';
    }
    $output .= ' <tr><th>' . t('Hostname') . '</th><td>';
    if ($record->ip) {
      $output .= gethostbyaddr($record->ip) . ' (' . l(t('whois'), 'http://www.whois.sc/' . $record->ip) . ')</td></tr>';
    }
    else {
    $output .= '<span style="color:red">Possible Proxy Settings Error:</span> No hostname reported</td></tr>';
    }
    $output .= ' <tr><th>' . t('Date') . '</th><td>' . $record->localdate . '</td></tr>';
    $output .= ' <tr><th>' . t('Request type') . '</th><td>' . $record->request_method . '</td></tr>';
    $output .= ' <tr><th>' . t('URI') . '</th><td>' . $record->request_uri . '</td></tr>';
    $output .= ' <tr><th>' . t('Protocol') . '</th><td>' . $record->server_protocol . '</td></tr>';
    $output .= ' <tr><th>' . t('User Agent') . '</th><td>' . $record->user_agent . '</td></tr>';
    $output .= ' <tr><th>' . t('Headers') . '</th><td>' . $record->http_headers . '</td></tr>';
    $output .= ' <tr><th>' . t('Request Entity') . '</th><td>' . $record->request_entity . '</td></tr>';
    $output .= ' <tr><th>' . t('Denied Reason') . '</th><td>' . $response['log'] . '</td></tr>';
    $output .= ' <tr><th>' . t('Explanation') . '</th><td>' . $response['explanation'] . '</td></tr>';
    $output .= ' <tr><th>' . t('Response') . '</th><td>' . $response['response'] . '</td></tr>';
    $output .= '</table>';
  }
  return $output;
}

/**
 * Builds the Settings form found on the /admin/settings/badbehavior page
 *
 * @return array
 * An array of data to be displayed in the Settings form.
 */
function badbehavior_settings_form() {

  $form['standard'] = array(
    '#type' => 'fieldset',
    '#title' => t('Standard Settings'),
  );
  $form['standard']['badbehavior_mail'] = array(
    '#type' => 'textfield',
    '#title' => t('Administrator Email'),
    '#default_value' => bb2_email(),
    '#description' => t('E-mail address for blocked users to contact in order to gain access.'),
    '#required' => TRUE,
  );
  $form['standard']['badbehavior_logging'] = array(
    '#type' => 'select',
    '#title' => t('Logging Verbosity'),
    '#options' => array(
      0 => t('Disabled'),
      1 => t('Normal'),
      'verbose' => t('Verbose'),
    ),
    '#default_value' => variable_get('badbehavior_logging', 1),
  );
  $form['standard']['badbehavior_log_timeformat'] = array(
    '#type' => 'select',
    '#title' => t('Logging Time Display Format'),
    '#options' => array(
      '24' => t('24-hour'),
      '12' => t('12-hour'),
    ),
    '#default_value' => variable_get('badbehavior_log_timeformat', '24'),
    '#description' => t('Set the <a href="/admin/reports/badbehavior">Bad Behavior Log</a> time display in 12- or 24-hour format.'),
  );
  $form['standard']['badbehavior_offsite_forms'] = array(
    '#type' => 'select',
    '#title' => t('Offsite Forms'),
    '#options' => array(
      'TRUE' => t('Allow'),
      'FALSE' => t('Deny'),
    ),
    '#default_value' => variable_get('badbehavior_offsite_forms', 'FALSE'),
    '#description' => t('Bad Behavior normally prevents your site from receiving data posted from forms on other web sites. This prevents spammers from, e.g., using a Google cached version of your web site to send you spam. There are certain reasons why you might want to enable this, such as when using remote login apps to allow users to login to your website from offsite locations.'),
  );
  $form['standard']['badbehavior_strict'] = array(
    '#type' => 'checkbox',
    '#title' => 'Enable strict mode',
    '#default_value' => variable_get('badbehavior_strict', 0),
    '#description' => t('Bad Behavior operates in two blocking modes: normal and strict. When strict mode is enabled, some additional checks for buggy software which have been spam sources are enabled, but occasional legitimate users using the same software (usually corporate or government users using very old software) may be blocked as well. It is up to you whether you want to have the government reading your blog, or keep away more spammers.'),
  );

// Project Honey Pot support.
  $form['httpbl'] = array(
    '#type' => 'fieldset',
    '#title' => t('Project Honey Pot Support'),
  );
  $form['httpbl']['badbehavior_httpbl_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Your http:BL Access Key'),
    '#default_value' => variable_get('badbehavior_httpbl_key', ''),
    '#maxlength' => 12,
    '#size' => 12,
    '#description' => t("To enable Bad Behavior's http:BL features you must have an <a href=\"@httpbl-url\">http:BL Access Key</a>.", array('@httpbl-url' => 'http://www.projecthoneypot.org/httpbl_configure.php')),
  );
  $form['httpbl']['badbehavior_httpbl_threat'] = array(
    '#type' => 'textfield',
    '#title' => t('http:BL Threat Level'),
    '#default_value' => variable_get('badbehavior_httpbl_threat', 25),
    '#maxlength' => 3,
    '#size' => 3,
    '#description' => t("This number provides a measure of how suspicious an IP address is, based on activity observed at Project Honey Pot. Bad Behavior will block requests with a threat level equal or higher to this setting. Project Honey Pot has <a href=\"@httpblthreat-url\">more information</a> on this parameter.", array('@httpblthreat-url' => 'http://www.projecthoneypot.org/threat_info.php')),
  );
  $form['httpbl']['badbehavior_httpbl_age'] = array(
    '#type' => 'textfield',
    '#title' => t('http:BL Maximum Threat Age'),
    '#default_value' => variable_get('badbehavior_httpbl_age', 30),
    '#maxlength' => 2,
    '#size' => 2,
    '#description' => t('This is the number of days since suspicious activity was last observed from an IP address by Project Honey Pot. Bad Behavior will block requests with a maximum age equal to or less than this setting.'),
  );
  $form['httpbl']['badbehavior_httpbl_quicklink'] = array(
    '#type' => 'textfield',
    '#title' => t('Project Honey Pot QuickLink'),
    '#default_value' => variable_get('badbehavior_httpbl_quicklink', ''),
    '#maxlength' => 255,
    '#description' => t("To include a hidden Project Honey Pot QuickLink to your website to help the anti-spam community trap unsuspecting spam bots, enter your QuickLink URL (with the 'http://') in this field. Create a free Project Honey Pot account and find <a href=\"@httpblquicklinks-url\">more information</a> about QuickLinks.", array('@httpblquicklinks-url' => 'http://www.projecthoneypot.org/manage_quicklink.php')),
  );
  $form['httpbl']['badbehavior_httpbl_quicklinktext'] = array(
    '#type' => 'textfield',
    '#title' => t('Project Honey Pot QuickLink Hidden Text'),
    '#default_value' => variable_get('badbehavior_httpbl_quicklinktext', 'Customize This'),
    '#maxlength' => 99,
    '#description' => t("If you entered a QuickLink, put a plain text phrase or word in this field that could be a relevant link title for your website (to fool the spam-bots). See <a href=\"@httpblquicklinks-url\">more information here</a> about QuickLinks text. (This field will not be used if there is no QuickLinks URL defined in the Project Honey Pot QuickLink field above)", array('@httpblquicklinks-url' => 'http://www.projecthoneypot.org/manage_quicklink.php')),
  );

// Reverse proxy and load balancer support.
  $form['proxy'] = array(
    '#type' => 'fieldset',
    '#title' => t('Reverse Proxy/Load Balancer Support (Advanced Option)'),
    '#description' => t('Set this option <em>only</em> when using Bad Behavior behind a reverse proxy or load balancer. See the README.txt for details.'),
  );
  $form['proxy']['badbehavior_reverse_proxy'] = array(
    '#type' => 'checkbox',
    '#title' => 'Enable Reverse Proxy Support',
    '#default_value' => variable_get('badbehavior_reverse_proxy', variable_get('reverse_proxy', 0)),
    '#description' => t('When enabled, Bad Behavior will assume it is receiving a connection from a reverse proxy, when a specific HTTP header is received. This option is enabled by default when you enable Drupal\'s built-in reverse_proxy option.'),
  );
  $form['proxy']['badbehavior_reverse_proxy_header'] = array(
    '#type' => 'textfield',
    '#title' => t('Reverse Proxy Header'),
    '#default_value' => variable_get('badbehavior_reverse_proxy_header', variable_get('reverse_proxy_header', 'X-Forwarded-For')),
    '#maxlength' => 99,
    '#description' => t('If you enabled the Reverse Proxy support, you may specify a header to use to help Bad Behavior identify the IP address of the proxy server.'),
  );
  return system_settings_form($form);
}


/**
 * Settings admimistration form field validation
 *
 * @param $form
 *
 * @param $form_state
 */
function badbehavior_settings_form_validate($form, &$form_state) {
  $values = $form_state['values'];
  if (!empty($values['badbehavior_httpbl_quicklink'])) {
    if (!valid_url($values['badbehavior_httpbl_quicklink'], $absolute = TRUE)) {
    form_set_error('badbehavior_httpbl_quicklink', t('You must enter a valid Project Honey Pot QuickLink URL, or leave the field blank.'));
    }
  }
  if (!preg_match("/^[a-zA-Z0-9 -]+$/", $values['badbehavior_httpbl_quicklinktext'])) {
  form_set_error('badbehavior_httpbl_quicklinktext', t('You must enter only alphanumeric characters, dashes, and spaces in the Project Honey Pot QuickLink Hidden Text field. (no special characters or symbols)'));
  }
  if (!preg_match("/^[a-zA-Z0-9 -_]+$/", $values['badbehavior_reverse_proxy_header'])) {
  form_set_error('badbehavior_reverse_proxy_header', t('You must enter only alphanumeric characters, dashes, spaces, and underscores in the Reverse Proxy Header field. (no other special characters or symbols)'));
  }
  if (!empty($values['badbehavior_httpbl_key'])) {
    if (!preg_match("/^[a-zA-Z]{12}$/", $values['badbehavior_httpbl_key'])) {
    form_set_error('badbehavior_httpbl_key', t('You must enter a valid Project Honey Pot http:BL Key, which can only be exactly 12 alphabetic characters. (no numbers or symbols) Leave the field blank to disable this function.'));
    }
  }
  if (!valid_email_address($values['badbehavior_mail'])) {
    form_set_error('badbehavior_mail', t('The e-mail address %mail is not valid.', array('%mail' => $values['badbehavior_mail'])));
  }
  if ((!is_numeric($values['badbehavior_httpbl_threat']))||($values['badbehavior_httpbl_threat'] < 0)||($values['badbehavior_httpbl_threat'] >= 256)) {
    form_set_error('badbehavior_httpbl_threat', t('You must enter only a number from 0 to 255 for the http:BL Threat Level.'));
  }
  if ((!is_numeric($values['badbehavior_httpbl_age']))||($values['badbehavior_httpbl_age'] <= 0)||($values['badbehavior_httpbl_age'] >= 61)) {
    form_set_error('badbehavior_httpbl_age', t('You must enter only a number from 1 to 60 for the http:BL Maximum Threat Age.'));
  }
}
