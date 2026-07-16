<?php

namespace Drupal\backup_migrate\Drupal\Destination;

use Drupal\backup_migrate\Core\Destination\ReadableDestinationInterface;
use Drupal\backup_migrate\Core\File\BackupFileInterface;
use Drupal\backup_migrate\Core\File\ReadableStreamBackupFile;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the drupal browser upload destination class.
 *
 * @package Drupal\backup_migrate\Core\Destination
 */
class DrupalBrowserUploadDestination extends PluginBase implements ReadableDestinationInterface {

  /**
   * Constructs a DrupalBrowserUploadDestination object.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $requestStack
   *   The request stack.
   */
  public function __construct(
    $init = [],
    protected readonly ?RequestStack $requestStack = NULL,
  ) {
    parent::__construct($init);
  }

  /**
   * {@inheritdoc}
   */
  public function getFile($id) {
    $out = NULL;
    $request = $this->requestStack ? $this->requestStack->getCurrentRequest() : NULL;
    $uploads = $request ? $request->files->get("files", NULL, TRUE) : NULL;
    $file_upload = is_array($uploads) && isset($uploads[$id]) ? $uploads[$id] : NULL;
    // Make sure there's an upload to process.
    if (!empty($file_upload)) {
      $out = new ReadableStreamBackupFile($file_upload->getRealPath());
      $out->setFullName($file_upload->getClientOriginalName());
    }
    return $out;
  }

  /**
   * Load the metadata for the given file however it may be stored.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileInterface $file
   *   The backup file.
   *
   * @return \Drupal\backup_migrate\Core\File\BackupFileInterface
   *   The requested integer.
   */
  public function loadFileMetadata(BackupFileInterface $file) {
    return $file;
  }

  /**
   * Load the file with the given ID from the destination.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileInterface $file
   *   The backup file.
   *
   * @return \Drupal\backup_migrate\Core\File\BackupFileReadableInterface
   *   The file if it exists or NULL if it doesn't
   */
  public function loadFileForReading(BackupFileInterface $file) {
    return $file;
  }

  /**
   * {@inheritdoc}
   */
  public function fileExists($id) {
    $request = $this->requestStack ? $this->requestStack->getCurrentRequest() : NULL;
    return $request ? (bool) $request->files->has("files[$id]") : FALSE;
  }

}
