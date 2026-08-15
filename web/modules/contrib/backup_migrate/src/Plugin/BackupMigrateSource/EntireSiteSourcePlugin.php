<?php

namespace Drupal\backup_migrate\Plugin\BackupMigrateSource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Drupal\Source\DrupalMySQLiSource;
use Drupal\backup_migrate\Core\Main\BackupMigrateInterface;
use Drupal\backup_migrate\Drupal\EntityPlugins\SourcePluginBase;
use Drupal\backup_migrate\Drupal\Source\DrupalSiteArchiveSource;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an default database source plugin.
 *
 * @BackupMigrateSourcePlugin(
 *   id = "EntireSite",
 *   title = @Translation("Entire Site (do not use)"),
 *   description = @Translation("Back up the entire Drupal site. This is not recommended for use on most sites, hopefully it will be fixed in a future release."),
 *   locked = true
 * )
 */
class EntireSiteSourcePlugin extends SourcePluginBase implements ContainerFactoryPluginInterface {

  /**
   * The database source plugin.
   *
   * @var \Drupal\backup_migrate\Core\Plugin\PluginInterface
   */
  protected $dbSource;

  /**
   * Constructs an EntireSiteSourcePlugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly TimeInterface $time,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('datetime.time')
    );
  }

  /**
   * Get the Backup and Migrate plugin object.
   *
   * @return Drupal\backup_migrate\Core\Plugin\PluginInterface
   *   The requested integer.
   */
  public function getObject() {
    // Add the default database.
    $info = Database::getConnectionInfo('default', 'default');
    $info = $info['default'];
    if ($info['driver'] == 'mysql') {
      $conf = $this->getConfig();
      $conf->set('directory', DRUPAL_ROOT);
      $this->dbSource = new DrupalMySQLiSource(new Config($info), $this->fileSystem);
      return new DrupalSiteArchiveSource($conf, $this->dbSource, $this->time);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function alterBackupMigrate(BackupMigrateInterface $bam, $key, array $options = []) {
    if ($source = $this->getObject()) {
      $bam->sources()->add($key, $source);
      // @todo Enable this, fix it.
      // $bam->sources()->add('default_db', $this->dbSource);
    }
  }

}
