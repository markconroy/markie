<?php
/**
 * @file
 * Contains drush integration for mothership.
 *
 * Alot of this is based on zen's drush integration, so send them the beer!
 * @see http://drupalcode.org/project/zen.git/blob/refs/heads/7.x-5.x:/zen-internals/zen.drush.inc
 */

/**
 * Implements hook_drush_command().
 */
function mothership_drush_command() {
  $items = array();
  $items['mothership'] = array(
    'description' => 'Create a mothership sub-theme.',
    'arguments' => array(
      'name' => dt('The name of your sub-theme.'),
      'machine-name' => dt('[optional] A machine-readable name for your sub-theme.'),
    ),
    'options' => array(
      'name' => dt('The name of your sub-theme.'),
      'machine-name' => dt('A machine-readable name for your sub-theme.'),
      'description' => dt('A description for your sub-theme.'),
      'site-name' => dt('The sitename directory where you wish the sub-theme to be installed. Defaults to the sitename where Mothership itself is located.'),
    ),
    'examples' => array(
      'drush mothership "Daughter of the Mothership"' => dt('Create a sub-theme called `Daughter of the Mothership` with the automatically generated machine name `daughter_of_the_mothership`.'),
      'drush mothership "Daughter of the Mothership" childship' => dt('Create a sub-theme called `Daughter of the Mothership` with the machine name `childship`.'),
      'drush mothership "Daughter of the Mothership" childship --site-name=all' => dt('Create a sub-theme called `Daughter of the Mothership` in the sites/all/themes/childship.'),
    ),
  );
  return $items;
}

/**
 * Create a copy of NEWTHEME renamed to specified machine name together with
 * all other references to NEWTHEME.
 */
function drush_mothership($name = NULL, $machine_name = NULL) {
  if (!isset($name)) {
    $name = drush_get_option('name');
  }

  // Figure out the machine name of the subtheme, either use the one passed by
  // the user or generate one from the name of the theme.
  if (!isset($machine_name)) {
    $machine_name = drush_get_option('machine-name');
  }
  if (!$machine_name) {
    $machine_name = $name;
  }
  $machine_name = drupal_clean_css_identifier(strtolower($machine_name), array(' ' => '_', '_' => '_', '/' => '', '[' => '', ']' => ''));

  $mothership_path = drush_locate_root() . '/' . drupal_get_path('theme', 'mothership');
  $newtheme_path = dirname($mothership_path) . '/NEWTHEME';

  // Figure out the path to the subtheme, if a user didn't specify a sitename
  // directory use the same as mothership itself.
  if ($site_name = drush_get_option('site-name')) {
    $subtheme_path = drush_locate_root() . '/sites/' . $site_name . '/themes/' . $machine_name;
    $subtheme_dir = dirname($subtheme_path);
    // Make sure the themes folder exist within the sitename directory.
    if (!is_dir($subtheme_dir)) {
      return drush_set_error('DRUSH_MOTHERSHIP_COPY_DIR', dt('Destination directory !path does not exist. You have to create it.', array(
        '!path' => $subtheme_dir,
      )));
    }
  }
  // The user didn't provide a sitename so we extract it from where mothership
  // itself is installed.
  else {
    $path = explode('/', $mothership_path);
    $path = array_slice($path, 0, -2);
    $subtheme_path = implode('/', $path) . '/' . $machine_name;
  }

  // Create a renamed copy of motherships example subtheme.
  if (!drush_copy_dir($newtheme_path, $subtheme_path)) {
    return drush_set_error('DRUSH_MOTHERSHIP_COPY_DIR', dt('Subtheme generation failed, exiting.'));
  }

  $subtheme_info_file = $subtheme_path . '/' . $machine_name . '.info';
  // Activate the info file and rename it to the new machine name.
  drush_op('rename', $subtheme_path . '/NEWTHEME.info.example', $subtheme_info_file);
  // Rename all references to NEWTHEME and change the theme description if the
  // user provided an alternative.
  $alterations = array(
    'NEWTHEME' => $name
  );
  if ($description = drush_get_option('description')) {
    $alterations += array("= Hi im $name based on mothership - you should really rename me :)" => "= $description");
  }
  drush_op('mothership_file_str_replace', $subtheme_info_file, array_keys($alterations), $alterations);
  drush_op('mothership_file_str_replace', $subtheme_path . '/template.php', 'NEWTHEME', $machine_name);

  drush_print(dt('Mothership subtheme "!name" created in: !path', array(
    '!name' => $name,
    '!path' => $subtheme_path,
  )));
  drush_print(dt('Visit your themes settings page and configure it to your liking: !path', array(
    '!path' => url('admin/appearance/settings/' . $machine_name, array('absolute' => TRUE)),
  )));
}

/**
 * Do string replacement within a specified file.
 */
function mothership_file_str_replace($file_path, $find, $replace) {
  $file_contents = file_get_contents($file_path);
  $file_contents = str_replace($find, $replace, $file_contents);
  file_put_contents($file_path, $file_contents);
}
