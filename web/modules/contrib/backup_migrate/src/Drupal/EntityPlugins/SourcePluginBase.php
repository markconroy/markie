<?php

namespace Drupal\backup_migrate\Drupal\EntityPlugins;

use Drupal\backup_migrate\Core\Main\BackupMigrateInterface;

/**
 * Provides the source plugin base class.
 *
 * @package Drupal\backup_migrate\Drupal\EntityPlugins
 */
abstract class SourcePluginBase extends WrapperPluginBase implements SourcePluginInterface {

  /**
   * {@inheritdoc}
   */
  public function alterBackupMigrate(BackupMigrateInterface $bam, $key, array $options = []) {
    $bam->sources()->add($key, $this->getObject());
  }

}
