<?php

namespace Drupal\backup_migrate\Drupal\Source;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Source\FileDirectorySource;

/**
 * Provides the drupal public files source class.
 *
 * @package Drupal\backup_migrate\Drupal\Source
 */
class DrupalPublicFilesSource extends FileDirectorySource {

  /**
   * Get the default values for the plugin.
   *
   * @return \Drupal\backup_migrate\Core\Config\Config
   *   The return value.
   */
  public function configDefaults() {
    $config = [
      'directory' => 'public://',
    ];

    return new Config($config);
  }

}
