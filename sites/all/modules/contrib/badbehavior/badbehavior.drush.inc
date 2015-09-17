<?php

/**
 * @file
 * Drush integration for the badbehavior module.
 */

/**
 * Implements hook_drush_command().
 */
function badbehavior_drush_command() {
  $items['badbehavior-dl'] = array(
    'description' => dt('Downloads the required BadBehavior library from svn.wp-plugins.org.'),
    'options' => array(
      '--path' => dt('Optional. A path to the download folder. If omitted, Drush will use the default location (sites/all/libraries/bad-behavior).'),
    ),
  );
  return $items;
}

/**
 * Download the BadBehavior library from SVN.
 */
function drush_badbehavior_dl() {
  $path = drush_get_option('path', drush_get_context('DRUSH_DRUPAL_ROOT') . '/sites/all/libraries/bad-behavior');
  if (drush_shell_exec('svn checkout http://svn.wp-plugins.org/bad-behavior/branches/2.2/ ' . $path)) {
    drush_log(dt('BadBehavior has been downloaded to @path.', array('@path' => $path)), 'success');
  }
  else {
    drush_log(dt('Drush was unable to download the BadBehavior to @path.', array('@path' => $path)), 'error');
  }
}

/**
 * Implements drush_MODULE_post_COMMAND().
 */
function drush_badbehavior_post_pm_enable() {
  $modules = func_get_args();
  if (in_array('badbehavior', $modules)) {
    drush_badbehavior_dl();
  }
}
