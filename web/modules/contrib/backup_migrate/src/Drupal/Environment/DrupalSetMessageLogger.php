<?php

namespace Drupal\backup_migrate\Drupal\Environment;

use Drupal\Core\Messenger\MessengerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Sends messages to the browser when B&M Migrate is run in interactive mode.
 *
 * @package Drupal\backup_migrate\Drupal\Environment
 */
class DrupalSetMessageLogger extends AbstractLogger {

  /**
   * Constructs a DrupalSetMessageLogger object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface|null $messenger
   *   The messenger.
   */
  public function __construct(
    protected readonly ?MessengerInterface $messenger = NULL,
  ) {}

  /**
   * Logs with an arbitrary level.
   *
   * @param mixed $level
   *   The level.
   * @param string $message
   *   The message.
   * @param array $context
   *   The context.
   */
  public function log($level, $message, array $context = []): void {
    // Translate the PSR logging level to a drupal message type.
    switch ($level) {
      case LogLevel::EMERGENCY:
      case LogLevel::ALERT:
      case LogLevel::CRITICAL:
      case LogLevel::ERROR:
        $type = 'error';
        break;

      case LogLevel::WARNING:
      case LogLevel::NOTICE:
        $type = 'warning';
        break;

      default:
        $type = 'status';
        break;
    }

    // @todo Handle translations properly.
    if ($this->messenger) {
      $this->messenger->addMessage($message, $type, FALSE);
    }
  }

}
