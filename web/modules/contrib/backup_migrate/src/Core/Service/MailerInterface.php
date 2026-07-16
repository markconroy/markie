<?php

namespace Drupal\backup_migrate\Core\Service;

/**
 * Interface MailSenderInterface.
 *
 * @package Drupal\backup_migrate\Core\Environment
 */
interface MailerInterface {

  /**
   * Sends an email message.
   *
   * @param string $key
   *   The configuration key.
   *   The key of the email the will be sent.
   * @param string|array $to
   *   The to.
   *   An RFC 2822 formatted to string or an array of them.
   * @param string $subject
   *   The subject.
   *   The subject of the email to be sent.
   * @param string $body
   *   The body.
   *   The body of the message being sent.
   * @param array $replacements
   *   The replacements.
   *   An array of string replacements for both the body and the subject.
   * @param array $additional_headers
   *   The additional headers.
   *   Additional headers to be added to the email if any.
   *
   * @return mixed
   *   The return value.
   */
  public function send($key, $to, $subject, $body, array $replacements = [], array $additional_headers = []);

}
