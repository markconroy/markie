<?php

namespace Drupal\Core\Updater;

/**
 * Defines a class for updating themes.
 *
 * Uses Drupal\Core\FileTransfer\FileTransfer classes via authorize.php.
 *
 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no
 *   replacement. Use composer to manage the code for your site.
 *
 * @see https://www.drupal.org/node/3512364
 */
class Theme extends Updater implements UpdaterInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($source, $root) {
    @trigger_error('The ' . __NAMESPACE__ . '\Theme class is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is no replacement. Use composer to manage the code for your site. See https://www.drupal.org/node/3512364', E_USER_DEPRECATED);
    parent::__construct($source, $root);
  }

  /**
   * Returns the directory where a theme should be installed.
   *
   * If the theme is already installed,
   * \Drupal::service('extension.list.theme')->getPath() will return a valid
   * path and we should install it there. If we're installing a new theme, we
   * always want it to go into /themes, since that's where all the
   * documentation recommends users install their themes, and there's no way
   * that can conflict on a multi-site installation, since the Update manager
   * won't let you install a new theme if it's already found on your system,
   * and if there was a copy in the top-level we'd see it.
   *
   * @return string
   *   The absolute path of the directory.
   */
  public function getInstallDirectory() {
    if ($this->isInstalled() && ($relative_path = \Drupal::service('extension.list.theme')->getPath($this->name))) {
      // The return value of
      // \Drupal::service('extension.list.theme')->getPath() is always relative
      // to the site, so prepend DRUPAL_ROOT.
      return DRUPAL_ROOT . '/' . dirname($relative_path);
    }
    else {
      // When installing a new theme, prepend the requested root directory.
      return $this->root . '/' . $this->getRootDirectoryRelativePath();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getRootDirectoryRelativePath() {
    return 'themes';
  }

  /**
   * {@inheritdoc}
   */
  public function isInstalled() {
    // Check if the theme exists in the file system, regardless of whether it
    // is enabled or not.
    $themes = \Drupal::state()->get('system.theme.files', []);
    return isset($themes[$this->name]);
  }

  /**
   * {@inheritdoc}
   */
  public static function canUpdateDirectory($directory) {
    $info = static::getExtensionInfo($directory);

    return (isset($info['type']) && $info['type'] == 'theme');
  }

  /**
   * Determines whether this class can update the specified project.
   *
   * @param string $project_name
   *   The project to check.
   *
   * @return bool
   *   TRUE if the the project can be updated, FALSE otherwise.
   */
  public static function canUpdate($project_name) {
    return (bool) \Drupal::service('extension.list.theme')->getPath($project_name);
  }

}
