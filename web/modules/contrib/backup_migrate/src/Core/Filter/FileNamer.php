<?php

namespace Drupal\backup_migrate\Core\Filter;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Plugin\FileProcessorInterface;
use Drupal\backup_migrate\Core\Plugin\FileProcessorTrait;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Utility\Token;

/**
 * Provides the file namer class.
 *
 * @package Drupal\backup_migrate\Core\Filter
 */
class FileNamer extends PluginBase implements FileProcessorInterface {
  use FileProcessorTrait;

  /**
   * Constructs a FileNamer object.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface|null $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Utility\Token|null $token
   *   The token service.
   */
  public function __construct(
    $init = [],
    protected readonly ?ModuleHandlerInterface $moduleHandler = NULL,
    protected readonly ?Token $token = NULL,
  ) {
    parent::__construct($init);
  }

  /**
   * {@inheritdoc}
   */
  public function configSchema(array $params = []) {
    $schema = [];
    if ($this->moduleHandler && $this->moduleHandler->moduleExists('token')) {
      $must_match = '/^[\w\-_:\[\]]+$/';
      $must_match_err = $this->t('%title must contain only letters, numbers, dashes (-) and underscores (_). And Site Tokens.');
    }
    else {
      $must_match = '/^[\w\-_:]+$/';
      $must_match_err = $this->t('%title must contain only letters, numbers, dashes (-) and underscores (_).');
    }
    // Backup configuration.
    if ($params['operation'] == 'backup') {
      $schema['groups']['file'] = [
        'title' => 'Backup File',
      ];
      $schema['fields']['filename'] = [
        'group' => 'file',
        'type' => 'text',
        'title' => 'File Name',
        'must_match' => $must_match,
        'must_match_error' => $must_match_err,
        'min_length' => 1,
        // Allow a 200 character backup name leaving a generous 55 characters
        // for timestamp and extension.
        'max_length' => 200,
        'required' => TRUE,
      ];
      $schema['fields']['timestamp'] = [
        'group' => 'file',
        'type' => 'boolean',
        'title' => 'Append a timestamp',
      ];
      $schema['fields']['timestamp_format'] = [
        'group' => 'file',
        'type' => 'text',
        'title' => 'Timestamp Format',
        'max_length' => 32,
        'dependencies' => ['timestamp' => TRUE],
        'description' => $this->t('Use <a href="http://php.net/date">PHP Date formatting</a>.'),
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
      'filename' => 'backup',
      'timestamp' => TRUE,
      'timestamp_format' => 'Y-m-d\TH-i-s',
    ]);
  }

  /**
   * Get a list of supported operations and their weight.
   *
   * @return array
   *   A render or configuration array.
   */
  public function supportedOps() {
    return [
      'afterBackup' => [],
    ];
  }

  /**
   * Run on a backup. Name the backup file according to the configuration.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileReadableInterface $file
   *   The backup file.
   *
   * @return \Drupal\backup_migrate\Core\File\BackupFileReadableInterface
   *   The requested integer.
   */
  public function afterBackup(BackupFileReadableInterface $file) {
    if ($this->moduleHandler && $this->moduleHandler->moduleExists('token') && $this->token) {
      $name = $this->token->replace($this->confGet('filename'));
    }
    else {
      $name = $this->confGet('filename');
    }
    if ($this->confGet('timestamp')) {
      $name .= '-' . date($this->confGet('timestamp_format'));
    }
    $file->setName($name);
    return $file;
  }

}
