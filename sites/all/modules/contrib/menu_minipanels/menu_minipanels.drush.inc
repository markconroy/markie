<?php
/**
 * @file
 * Drush integration for Menu_MiniPanels.
 */

/**
 * Implements hook_drush_command().
 */
function menu_minipanels_drush_command() {
  $items = array();
  $items['download-qtip'] = array(
    'callback' => 'menu_minipanels_drush_download',
    'description' => dt('Downloads the required qTip JavaScript library from craigsworks.com.'),
    'arguments' => array(
      'path' => dt('Optional. A path to the download folder. If omitted Drush will use the default location (sites/all/libraries/qtip).'),
    ),
  );
  return $items;
}

/**
 * Download the qtip script.
 */
function menu_minipanels_drush_download() {
  $url = "http://craigsworks.com/projects/qtip/download/package/production/development/";

  $args = func_get_args();
  if (isset($args[0])) {
    $path = $args[0];
  }
  else {
    $path = drush_get_context('DRUSH_DRUPAL_ROOT');
    if (module_exists('libraries') && $lib_path = libraries_get_path('qtip')) {
      $path .= '/' . $lib_path;
    }
    else {
      $path .= '/sites/all/libraries/qtip';
    }
  }

  // Ensure the directory exists, if it doesn't exist, recursively create it.
  if (!is_dir($path) && !mkdir($path, 0777, TRUE)) {
    drush_log(dt('Drush was unable to create the directory @path.', array('@path' => $path)), 'error');
    return;
  }

  $olddir = getcwd();
  drush_op('chdir', $path);
  $tmp_file = 'qtip.zip';

  // Download the file.
  system_retrieve_file($url, './' . $tmp_file, FALSE, FILE_EXISTS_REPLACE);

  if (file_exists($tmp_file) || drush_get_context('DRUSH_SIMULATE')) {
    if (!drush_shell_exec("unzip -o %s", $tmp_file)) {
      drush_op('chdir', $olddir);
      return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', 'Drush was unable to extract qTip from ' . $tmp_file . ', ensure the \'unzip\' command is in your command path. You may also manually extract the file.');
    }
    else {
      drush_log(dt('qTip has been downloaded & extracted to @path.', array('@path' => $path)), 'success');
    }
  }
  else {
    drush_op('chdir', $olddir);
    return drush_set_error('DRUSH_PM_DOWNLOAD_FAILED', 'Drush was unable to download qTip from ' . $url . '.');
  }
}

/**
 * Implements drush_MODULE_post_COMMAND().
 */
function drush_menu_minipanels_post_enable() {
  $modules = func_get_args();
  if (in_array('menu_minipanels', $modules)) {
    menu_minipanels_drush_download();
  }
}
