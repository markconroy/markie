<?php
/**
 * @file
 * Implements the configuration of the Cookie Control pop-up dialog.
 *
 * Module developed by Ixis www.ixis.co.uk
 * JavaScript by Civic UK www.civicuk.com
 */

/**
 * cookicontrol_admin_settings function.
 *
 * @access public
 * @return void
 */
function cookiecontrol_admin_settings() {
  $form = array();
  $path = drupal_get_path('module', 'cookiecontrol');

  $form['interface'] = array(
    '#type' => 'fieldset',
    '#title' => t('User Interface'),
  );
  $form['interface']['cookiecontrol_text'] = array(
    '#type' => 'textarea',
    '#title' => t('Introduction Text'),
    '#default_value' => variable_get('cookiecontrol_text', COOKIECONTROL_TEXT),
    '#description' => t('A short message requesting consent to use cookies on this site. HTML markup is allowed for adding links and paragraphs.'),
  );

  $form['interface']['cookiecontrol_fulltext'] = array(
    '#type' => 'textarea',
    '#title' => t('Full Text'),
    '#default_value' => variable_get('cookiecontrol_fulltext', COOKIECONTROL_FULLTEXT),
    '#description' => t('A further information about the use of cookies on this site. HTML markup is allowed for adding links and paragraphs.'),
  );

  $cookiecontrol_privacynode = variable_get('cookiecontrol_privacynode', '');
  $form['interface']['cookiecontrol_privacynode'] = array(
    '#type' => 'textfield',
    '#size' => 5,
    '#maxlength' => 7,
    '#title' => t('Privacy Policy Link'),
    '#field_prefix' => '?q=node/',
    '#default_value' => $cookiecontrol_privacynode,
    '#description' => t("Specify a node ID which represents your site's privacy policy. This will be appended to the full text message text above."),
    '#required' => FALSE,
  );
  // Display a link to the current privacy policy if set
  if ($cookiecontrol_privacynode > 0) {
    $form['interface']['cookiecontrol_privacynode']['#field_suffix'] = l(t('View existing privacy policy page'), 'node/' . $cookiecontrol_privacynode, array('attributes' => array('target' => 'new')));
  }

  $form['interface']['cookiecontrol_consentmodel'] = array(
    '#type' => 'select',
    '#title' => 'Consent Model',
    '#options' => array(
      'information_only' => t('Information only'),
      'implicit' => t('Implicit'),
      'explicit' => t('Explicit')
    ),
    '#default_value' => variable_get('cookiecontrol_consentmodel', 'information_only'),
    '#description' => t('Select the consent model you wish Cookie Control to use. In each consent model the Cookie Control panel appears to the user when they first access the site.')
  );

  $form['interface']['cookiecontrol_shape'] = array(
    '#type' => 'radios',
    '#title' => t('Icon Shape'),
    '#description' => t("The icon will be anchored to the corner of the viewport and won't scroll with your web page."),
    '#default_value' => variable_get('cookiecontrol_shape', 'triangle'),
    '#options' => array(
      'triangle' => t('!icon Triangle', array('!icon' => theme('image', array('path' => $path . '/images/icon-triangle.png')))),
      'diamond' => t('!icon Diamond', array('!icon' => theme('image', array('path' => $path . '/images/icon-diamond.png'))))
    )
  );

  $form['interface']['cookiecontrol_position'] = array(
    '#type' => 'radios',
    '#title' => t('Icon Position'),
    '#description' => t("Choose whether you'd like your icon to appear on the bottom left or botom right of the browser window."),
    '#default_value' => variable_get('cookiecontrol_position', 'left'),
    '#options' => array(
      'left' => t('Left'),
      'right' => t('Right'),
    )
  );

  $form['interface']['cookiecontrol_theme'] = array(
    '#type' => 'radios',
    '#title' => t('Colour theme'),
    '#description' => t("Choose the colour set used on the widget. Selecting a contrast to your theme will help visitors see the pop-up."),
    '#default_value' => variable_get('cookiecontrol_theme', 'dark'),
    '#options' => array(
      'dark' => t('Dark'),
      'light' => t('Light'),
    )
  );

  $form['visibility'] = array(
    '#type' => 'fieldset',
    '#title' => t('Visibility'),
  );
  $form['visibility']['cookiecontrol_startopen'] = array(
    '#type' => 'checkbox',
    '#title' => t('Open the cookie information on page load.'),
    '#default_value' => variable_get('cookiecontrol_startopen', TRUE),
    '#description' => t('Visitors to the site will see the pop-up at the bottom of their browser on every page load until they acknowledge the pop-up.')
  );
  $form['visibility']['cookiecontrol_timeout'] = array(
    '#type' => 'textfield',
    '#size' => 5,
    '#title' => t('Automatic Hide Timeout'),
    '#field_suffix' => t('seconds'),
    '#default_value' => variable_get('cookiecontrol_timeout', '60'),
    '#description' => t("How long to keep the information pop-up displayed before it is automatically hidden. Once hidden the information can be accessed by clicking on the icon in the corner."),
    '#required' => FALSE,
  );
  $form['visibility']['cookiecontrol_hideonaccept'] = array(
    '#type' => 'checkbox',
    '#title' => t('Hide cookie control icon after accepted'),
    '#default_value' => variable_get('cookiecontrol_hideonaccept', FALSE),
    '#description' => t('Visitors who have given consent will not see the icon in the corner of their browser. This works by setting an additional cookie to keep the icon hidden. Changing this option later on will not affect existing visitors who have hidden their icon.')
  );
  $form['visibility']['cookiecontrol_countries'] = array(
    '#type' => 'textfield',
    '#title' => t('Country Restriction'),
    '#default_value' => variable_get('cookiecontrol_countries', ''),
    '#description' => t('Enter a comma separated list of country full names for which you wish the pop-up to appear. If left blank Cookie Control will appear for all users from all countries. The full list of countries can be found !here. %examples', array('%examples' => t('Example: United Kingdom, Germany'), '!here' => l(t('here'), 'http://www.geoplugin.com/iso3166', array('attributes' => array('target' => '_blank')) ))),
    '#maxlength' => 1000,
    '#size' => 128,
  );

  $form['advanced'] = array(
    '#type' => 'fieldset',
    '#title' => t('Advanced'),
    '#collapsed' => TRUE,
    '#collapsible' => TRUE
  );

  $form['advanced']['cookiecontrol_onaccept'] = array(
    '#type' => 'textarea',
    '#title' => t('On Cookie Accept'),
    '#default_value' => variable_get('cookiecontrol_onaccept', ''),
    '#description' => t('JavaScript code to be executed when a visitor gives consent for the site to use cookies. Example use can be to trigger analytics code to record the initial page visit.'),
  );

  $form['advanced']['cookiecontrol_exclude_paths'] = array(
    '#type' => 'textarea',
    '#title' => t('Exclude Paths'),
    '#default_value' => variable_get('cookiecontrol_exclude_paths', ''),
    '#description' => t('Specify pages, on which to not display the cookie control, by using their paths. Enter one path per line. The \'*\' character is a wildcard. Example paths are blog for the blog page and blog/* for every personal blog. %front is the front page.', array('%front' => '<front>')),
  );

  return system_settings_form($form);
}

/**
 * cookiecontrol_admin_settings_validate function.
 *
 * @access public
 * @param mixed $form
 * @param mixed &$form_state
 * @return void
 */
function cookiecontrol_admin_settings_validate($form, &$form_state) {
  // Attempt to load in a specified privacy policy node id
  if ($form_state['values']['cookiecontrol_privacynode'] > 0) {
    $node = node_load($form_state['values']['cookiecontrol_privacynode']);

    // If no node can be loaded give the user a suitable message prompt.
    if (!$node) {
      form_set_error('cookiecontrol_privacynode', t('The specified privacy policy node id does not exist. Leave blank if you have not yet created a policy page.'));
    }
  }

  // If the intro text is left blank we revert it to the default
  if ($form_state['values']['cookiecontrol_text'] == '') {
    form_set_value($form['interface']['cookiecontrol_text'], COOKIECONTROL_TEXT, $form_state);
  }

  // If the full text is left blank we revert it to the default
  if ($form_state['values']['cookiecontrol_fulltext'] == '') {
    form_set_value($form['interface']['cookiecontrol_fulltext'], COOKIECONTROL_FULLTEXT, $form_state);
  }

  // Wrap a generic piece of text with paragraph tags if nothing is detected.
  if (stripos($form_state['values']['cookiecontrol_text'], '<p>') === FALSE) {
    $form_state['values']['cookiecontrol_text'] = '<p>' . $form_state['values']['cookiecontrol_text'] . '</p>';
  }
    
  // Remove any newlines to avoid breaking JavaScript
  form_set_value($form['interface']['cookiecontrol_text'], str_replace(array("\r\n", "\n", "\r"), '', $form_state['values']['cookiecontrol_text']), $form_state);
  form_set_value($form['interface']['cookiecontrol_fulltext'], str_replace(array("\r\n", "\n", "\r"), '', $form_state['values']['cookiecontrol_fulltext']), $form_state);
}