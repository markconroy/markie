<?php

namespace Drupal\backup_migrate\Core\Service;

use Drupal\backup_migrate\Core\File\BackupFileWritableInterface;

/**
 * Interface ArchiveReaderInterface.
 *
 * @package Drupal\backup_migrate\Core\Service
 */
interface ArchiveWriterInterface {

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
   * @param \Drupal\backup_migrate\Core\File\BackupFileWritableInterface $out
   *   The out.
   */
  public function setArchive(BackupFileWritableInterface $out);

  /**
   * Adds a file to the archive.
   *
   * @param string $real_path
   *   The real path.
   *   The real path to the file. Can be a stream URI.
   * @param string $new_path
   *   The new path.
   *   The path that the file should have in the archive. Leave blank to use the
   *   original path.
   *
   * @return mixed
   *   The return value.
   */
  public function addFile($real_path, $new_path = '');

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
