<?php
/*
 * @file
 * Info hooks for variable module.
 */
/**
 * Variable definition
 */

/**
 * Implements hook_variable_group_info().
 */
function entityform_notifications_variable_group_info() {
  // Group for variable that have no group
  $groups['entityform'] = array(
    'title' => t('EntityForm Email'),
    'description' => t("Emails used by EntityForm."),
  );
  return $groups;
}

/**
 * Implements hook_variable_info().
 */
function entityform_notifications_variable_info($options) {

  $variable['entityform_notify_submitter_[mail_part]'] = array(
    'title' => t('Email Entityform Submitter'),
    'type' => 'mail_text',
    'description' => t('Template for the email sent out to the submitter.'),
    'children' => array(
      'entityform_notify_submitter_subject' => array(
        'default' => 'Thanks for the submitting the form [entityform-type:label]',
      ),
      'entityform_notify_submitter_body' => array(
        'default' => "We have received your submission of the form [entityform-type:label].\n\n[entityform-type:notification-text]",
      ),
    ),
    'group' => 'entityform',
  );

  $variable['entityform_notify_admin_[mail_part]'] = array(
    'title' => t('Email Entityform Admin'),
    'type' => 'mail_text',
    'description' => t('Template for the email sent out to administrators.'),
    'children' => array(
      'entityform_notify_admin_subject' => array(
        'default' => 'Form submission notification: [entityform-type:label]',
      ),
      'entityform_notify_admin_body' => array(
        'default' => '[entityform:entityform-submittd-data]',
      ),
    ),
    'group' => 'entityform',
  );

  return $variable;
}
