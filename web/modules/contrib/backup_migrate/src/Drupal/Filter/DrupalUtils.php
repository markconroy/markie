<?php

namespace Drupal\backup_migrate\Drupal\Filter;

use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Config\Config;
use Drupal\Core\State\StateInterface;

/**
 * Provides the drupal utils class.
 *
 * @package Drupal\backup_migrate\Drupal\Filter
 */
class DrupalUtils extends PluginBase {

  /**
   * Whether the site was put in maintenance mode before the operation.
   *
   * @var bool
   */
  protected $maintenanceMode;

  /**
   * Constructs a DrupalUtils object.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Drupal\Core\State\StateInterface|null $state
   *   The state service.
   */
  public function __construct(
    $init = [],
    protected readonly ?StateInterface $state = NULL,
  ) {
    parent::__construct($init);
  }

  /**
   * {@inheritdoc}
   */
  public function configSchema(array $params = []) {
    $schema = [];

    // Backup configuration.
    if ($params['operation'] == 'backup' || $params['operation'] == 'restore') {
      $schema['groups']['advanced'] = [
        'title' => 'Advanced Settings',
      ];
      $schema['fields']['site_offline'] = [
        'group' => 'advanced',
        'type' => 'boolean',
        'title' => $this->t('Take site offline'),
        'description' => $this->t('Take the site offline during backup and show a maintenance message. Site will be taken back online once the backup is complete.'),
      ];
    }
    return $schema;
  }

  /**
   * Get the default values for the plugin.
   *
   * @return \Drupal\backup_migrate\Core\Config\Config
   *   The return value.
   */
  public function configDefaults() {
    return new Config([
      'disable_query_log' => TRUE,
      'site_offline' => FALSE,
    ]);
  }

  /**
   * Run before the backup/restore begins.
   */
  public function setUp() {
    $this->takeSiteOffline();
  }

  /**
   * Run after the operation is complete.
   */
  public function tearDown() {
    $this->takeSiteOnline();
  }

  /**
   * Take the site offline if we need to.
   */
  protected function takeSiteOffline() {
    // Take the site offline.
    if ($this->state && $this->confGet('site_offline') && !$this->state->get('system.maintenance_mode')) {
      $this->state->set('system.maintenance_mode', TRUE);
      $this->maintenanceMode = TRUE;
    }
  }

  /**
   * Take the site online if it was taken offline for this operation.
   */
  protected function takeSiteOnline() {
    // Take the site online again.
    if ($this->maintenanceMode) {
      if ($this->state) {
        $this->state->set('system.maintenance_mode', FALSE);
      }
    }
  }

  /**
   * Perform actions before restoring the backup.
   *
   * This used to perform a file size check but it occurred *after* the file
   * was uploaded and uncompressed, which was a complete waste of time.
   *
   * @todo Remove this.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileReadableInterface $file
   *   The backup file.
   *
   * @return \Drupal\backup_migrate\Core\File\BackupFileReadableInterface
   *   The requested integer.
   */
  public function beforeRestore(BackupFileReadableInterface $file) {
    return $file;
  }

}
