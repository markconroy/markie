<?php

namespace Drupal\backup_migrate\Core\Service;

use Drupal\backup_migrate\Core\File\ReadableStreamBackupFile;

/**
 * Provides an interface for HTTP client requests.
 *
 * @package Drupal\backup_migrate\Core\Service
 */
interface HttpClientInterface {

  /**
   * Get the body of the given resource.
   *
   * @param string $url
   *   The URL.
   *
   * @return mixed
   *   The return value.
   */
  public function get($url);

  /**
   * Post the given data (as a string or an array) to the given URL.
   *
   * @param string $url
   *   The URL.
   * @param array $data
   *   The data.
   *
   * @return mixed
   *   The return value.
   */
  public function post($url, $data);

  /**
   * Post a file along with other data (as an array).
   *
   * @param string $url
   *   The URL.
   * @param \Drupal\backup_migrate\Core\File\ReadableStreamBackupFile $file
   *   The backup file.
   * @param array $data
   *   The data.
   *
   * @return mixed
   *   The return value.
   */
  public function postFile($url, ReadableStreamBackupFile $file, $data);

}
