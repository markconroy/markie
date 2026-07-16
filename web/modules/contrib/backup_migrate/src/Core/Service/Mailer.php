<?php

namespace Drupal\backup_migrate\Core\Service;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * A very basic mailer that uses the php mail function.
 *
 * In most systems this will be replaced by a wrapper around whatever mail
 * library is used in that system.
 *
 * @package Drupal\backup_migrate\Core\Environment
 */
class Mailer implements MailerInterface {

  /**
   * Constructs a Mailer object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   The mail manager.
   */
  public function __construct(
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly MailManagerInterface $mailManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function send($key, $to, $subject, $body, $replacements = [], $additional_headers = []) {
    // Combine the to objects.
    if (is_array($to)) {
      $to = implode(',', $to);
    }

    // Do the string replacement.
    if ($replacements) {
      $subject = strtr($subject, $replacements);
      $body = strtr($body, $replacements);
    }

    $langcode = $this->languageManager->getDefaultLanguage()->getId();
    $this->mailManager->mail('backup_migrate', $key, $to, $langcode, [
      'message' => $body,
      'subject' => $subject,
    ]);
  }

}
