<?php

namespace Drupal\backup_migrate\Core\Service;

use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

/**
 * Provides the tee logger class.
 *
 * @package Drupal\backup_migrate\Core\Tests\Service
 */
class TeeLogger extends AbstractLogger {

  /**
   * Stores the value.
   *
   * @var \Psr\Log\LoggerInterface[] The loggers
   */
  protected $loggers;

  /**
   * Handles the construct operation.
   *
   * @param \Psr\Log\LoggerInterface[] $loggers
   *   The loggers.
   */
  public function __construct(array $loggers) {
    $this->setLoggers($loggers);
  }

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
    foreach ($this->getLoggers() as $logger) {
      $logger->log($level, $message, $context);
    }
  }

  /**
   * Gets the loggers.
   *
   * @return \Psr\Log\LoggerInterface[]
   *   The requested integer.
   */
  public function getLoggers() {
    return $this->loggers;
  }

  /**
   * Sets the loggers.
   *
   * @param \Psr\Log\LoggerInterface[] $loggers
   *   The loggers.
   */
  public function setLoggers(array $loggers) {
    $this->loggers = $loggers;
  }

  /**
   * Handles the add logger operation.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function addLogger(LoggerInterface $logger) {
    $this->loggers[] = $logger;
  }

}
