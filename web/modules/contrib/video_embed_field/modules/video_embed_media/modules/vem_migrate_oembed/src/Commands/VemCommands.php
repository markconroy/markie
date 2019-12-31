<?php

namespace Drupal\vem_migrate_oembed\Commands;

use Drupal\vem_migrate_oembed\VemMigrate;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class VemCommands extends DrushCommands {

  /**
   * The migrate service.
   *
   * @var \Drupal\vem_migrate_oembed\VemMigrate
   */
  protected $migrator;

  /**
   * SamplerCommands constructor.
   *
   * @param \Drupal\vem_migrate_oembed\VemMigrate $migrator
   *   The migrate service.
   */
  public function __construct(VemMigrate $migrator) {
    parent::__construct();
    $this->migrator = $migrator;
  }

  /**
   * Migrates from video_embed_media to core media.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @command vem:migrate_oembed
   */
  public function migrate() {
    $this->migrator->migrate();
  }

}
