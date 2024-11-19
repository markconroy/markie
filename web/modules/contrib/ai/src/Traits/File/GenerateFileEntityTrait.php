<?php

namespace Drupal\ai\Traits\File;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

/**
 * Trait to add the possibility to store files directly in the processor.
 *
 * @package Drupal\ai\Traits\File
 */
trait GenerateFileEntityTrait {

  /**
   * Get as file entity.
   *
   * @param string $file_path
   *   The file path.
   * @param string $filename
   *   The filename.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   */
  public function getAsFileEntity($file_path = "", $filename = ""): File {
    // Set defaults.
    if (!$file_path) {
      $file_path = 'public://';
    }
    if (!$filename) {
      if ($this->filename) {
        $filename = $this->filename;
      }
      else {
        $filename = uniqid();
      }
    }
    // Get the directory from the file path.
    $directory = dirname($file_path);
    // Get the file system.
    $file_system = \Drupal::service('file_system');
    // Prepare the directory.
    $file_system->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $file_storage = \Drupal::service('entity_type.manager')->getStorage('file');
    // Generate a file from string and rename if it already exists.
    $file_url = substr($file_path, -2) == '//' ? $file_path . $filename : rtrim($file_path, '/') . '/' . $filename;
    $file_path = $file_system->saveData($this->getBinary(), $file_url, FileExists::Rename);

    // Generate a file entity.
    $file = $file_storage->create([
      'uri' => $file_path,
      'status' => 1,
      'uid' => \Drupal::currentUser()->id(),
      'filename' => basename($file_path),
    ]);
    $file->save();
    return $file;
  }

}
