<?php

namespace Drupal\backup_migrate\Core\Service;

use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;

/**
 * Interface ArchiveWriterInterface.
 *
 * @package Drupal\backup_migrate\Core\Environment
 */
interface ArchiveReaderInterface {

  /**
   * Get the file extension for this archiver.
   *
   * For a tarball writer this would be 'tar'. For a Zip file writer this would
   * be 'zip'.
   *
   * @return string
   *   The requested string.
   */
  public function getFileExt();

  /**
   * Sets the archive.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileReadableInterface $out
   *   The out.
   */
  public function setArchive(BackupFileReadableInterface $out);

  /**
   * Extract all files to the given directory.
   *
   * @param mixed $directory
   *   The directory.
   *
   * @return mixed
   *   The return value.
   */
  public function extractTo($directory);

  /**
   * This will be called when all files have been added.
   *
   * It gives the implementation a chance to clean up and commit the changes if
   * needed.
   *
   * @return mixed
   *   The return value.
   */
  public function closeArchive();

}
