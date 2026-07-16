<?php

namespace Drupal\backup_migrate\Drupal\Filter;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Plugin\FileProcessorInterface;
use Drupal\backup_migrate\Core\Plugin\FileProcessorTrait;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Translation\TranslatableTrait;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\backup_migrate\Core\File\BackupFileWritableInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Provides the drupal encrypt class.
 *
 * @package Drupal\backup_migrate\Drupal\Filter
 */
class DrupalEncrypt extends PluginBase implements FileProcessorInterface {

  use FileProcessorTrait;
  use TranslatableTrait;

  /**
   * Constructs a DrupalEncrypt object.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Drupal\Core\File\FileSystemInterface|null $fileSystem
   *   The file system service.
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   The messenger.
   */
  public function __construct(
    $init = [],
    protected readonly ?FileSystemInterface $fileSystem = NULL,
    protected readonly ?MessengerInterface $messenger = NULL,
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

      if (class_exists('\Defuse\Crypto\File')) {
        $schema['groups']['encrypt'] = [
          'title' => 'Backup Encryption',
        ];
        $schema['fields']['encrypt'] = [
          'group' => 'encrypt',
          'type' => 'boolean',
          'title' => $params['operation'] == 'backup' ? $this->t('Encrypt File') : $this->t('Decrypt file'),
          'description' => $this->t('Password for encrypting / decrypting the file'),
        ];
        $schema['fields']['encrypt_password'] = [
          'group' => 'encrypt',
          'type' => 'password',
          'title' => $params['operation'] == 'backup' ? $this->t('Encryption Password') : $this->t('Decryption Password'),
        ];
      }
      else {
        if ($this->messenger) {
          $this->messenger->addMessage(t('In order to encrypt backup files, please install the Defuse PHP-encryption library via Composer with the following command: <code>composer require defuse/php-encryption</code>. See the <a href="@docs">Defuse PHP Encryption Documentation Page</a> for more information.',
          [
            '@docs' => 'https://www.drupal.org/node/3185484',
          ]
          ), 'status');
        }
      }
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
      'encrypt' => FALSE,
    ]);
  }

  /**
   * Handles the encrypt file operation.
   */
  protected function encryptFile(BackupFileReadableInterface $from, BackupFileWritableInterface $to) {
    $path = $this->realpath($from->realpath());
    $out_path = $this->realpath($to->realpath());
    $crypto_file = '\Defuse\Crypto\File';
    if (!$path || !$out_path || !class_exists($crypto_file)) {
      return FALSE;
    }

    try {
      call_user_func([$crypto_file, 'encryptFileWithPassword'], $path, $out_path, $this->confGet('encrypt_password'));
      $fileszc = filesize($this->realpath($to->realpath()));
      $to->setMeta('filesize', $fileszc);
      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Handles the decrypt file operation.
   */
  protected function decryptFile(BackupFileReadableInterface $from, BackupFileWritableInterface $to) {
    $path = $this->realpath($from->realpath());
    $out_path = $this->realpath($to->realpath());
    $crypto_file = '\Defuse\Crypto\File';
    if (!$path || !$out_path || !class_exists($crypto_file)) {
      return FALSE;
    }

    try {
      call_user_func([$crypto_file, 'decryptFileWithPassword'], $path, $out_path, $this->confGet('encrypt_password'));

      return TRUE;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Resolves a path through Drupal's file system service.
   *
   * @param string $path
   *   The path to resolve.
   *
   * @return string|false
   *   The resolved real path, or FALSE on failure.
   */
  protected function realpath($path) {
    return $this->fileSystem ? $this->fileSystem->realpath($path) : FALSE;
  }

  /**
   * Handles the before restore operation.
   */
  public function beforeRestore(BackupFileReadableInterface $file) {
    $type = $file->getExtLast();
    if ($type == 'ssl' && $this->confGet('encrypt')) {
      $out = $this->getTempFileManager()->popExt($file);
      $success = $this->decryptFile($file, $out);
      if ($out && $success) {
        return $out;
      }
    }

    return $file;
  }

  /**
   * Handles the supported ops operation.
   */
  public function supportedOps() {
    return [
      'getFileTypes' => [],
      'backupSettings' => [],
      'afterBackup' => ['weight' => 1000],
      'beforeRestore' => ['weight' => -1000],
    ];
  }

  /**
   * Handles the after backup operation.
   */
  public function afterBackup(BackupFileReadableInterface $file) {
    if ($this->confGet('encrypt')) {
      $out = $this->getTempFileManager()->pushExt($file, 'ssl');
      $success = $this->encryptFile($file, $out);
      if ($out && $success) {
        return $out;
      }
    }

    return $file;
  }

}
