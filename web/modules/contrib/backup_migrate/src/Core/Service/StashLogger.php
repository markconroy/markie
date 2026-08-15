<?php

namespace Drupal\backup_migrate\Core\Service;

use Psr\Log\AbstractLogger;

/**
 * Saves log entries to memory to be processed during the current process.
 *
 * This simple service does no clearing or memory management so should not be
 * used for a long-running process.
 *
 * @package Drupal\backup_migrate\Core\Service
 */
class StashLogger extends AbstractLogger {

  /**
   * Stores the value.
   *
   * @var array The logs
   */
  protected $logs = [];

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
    $this->logs[] = [
      'level' => $level,
      'message' => $message,
      'context' => $context,
    ];
  }

  /**
   * Get all of the log messages that were saved to this stash.
   *
   * @return array
   *   A render or configuration array.
   */
  public function getAll() {
    return $this->logs;
  }

}
