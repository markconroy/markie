<?php

namespace Drupal\backup_migrate\Core\Service;

use Drupal\backup_migrate\Core\Exception\HttpClientException;
use Drupal\backup_migrate\Core\File\ReadableStreamBackupFile;

/**
 * Provides the php curl http client class.
 *
 * @package Drupal\backup_migrate\Core\Service
 */
class PhpCurlHttpClient implements HttpClientInterface {

  /**
   * Get the body of the given resource.
   *
   * @param string $url
   *   The URL.
   *
   * @return mixed
   *   The return value.
   */
  public function get($url) {
    // @todo Implement if needed.
  }

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
  public function post($url, $data) {
    $ch = $this->getCurlResource($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    return $this->curlExec($ch);
  }

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
  public function postFile($url, ReadableStreamBackupFile $file, $data) {
    $data['file'] = new \CURLFile($file->realpath());
    $data['file']->setPostFilename($file->getFullName());
    return $this->post($url, $data);
  }

  /**
   * Get the CURL Resource with default options.
   *
   * @param string $url
   *   The URL.
   *
   * @return resource
   *   The return value.
   */
  protected function getCurlResource($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    return $ch;
  }

  /**
   * Perform the http action and return the body or throw an exception.
   *
   * @param mixed $ch
   *   The ch.
   *
   * @return mixed
   *   The return value.
   *
   * @throws \Drupal\backup_migrate\Core\Exception\HttpClientException
   */
  protected function curlExec($ch) {
    $body = curl_exec($ch);
    if ($msg = curl_error($ch)) {
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if (!$code) {
        $info['code'] = curl_errno($ch);
      }
      throw new HttpClientException($msg, [], $code);
    }
    return $body;
  }

}
