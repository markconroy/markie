<?php

namespace Drupal\entity_usage_test;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\RfcLogLevel;
use Psr\Log\LoggerInterface;

/**
 * A test logger.
 */
class TestLogger implements LoggerInterface {

  /**
   * An array of log messages keyed by type.
   *
   * @var mixed[]
   */
  protected static array $logs;

  /**
   * TestLogger constructor.
   */
  public function __construct() {
    if (empty(static::$logs)) {
      $this->clear();
    }
  }

  /**
   * Returns an array of logs.
   *
   * @param string|false $level
   *   The log level to get logs for. FALSE returns all logs.
   *
   * @return mixed[]
   *   The array of log messages.
   */
  public function getLogs(string|false $level = FALSE): array {
    return FALSE === $level ? static::$logs : static::$logs[$level];
  }

  /**
   * Removes all log records.
   */
  public function clear(): void {
    static::$logs = [
      'emergency' => [],
      'alert' => [],
      'critical' => [],
      'error' => [],
      'warning' => [],
      'notice' => [],
      'info' => [],
      'debug' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    // Convert levels...
    static $map = [
      RfcLogLevel::DEBUG => 'debug',
      RfcLogLevel::INFO => 'info',
      RfcLogLevel::NOTICE => 'notice',
      RfcLogLevel::WARNING => 'warning',
      RfcLogLevel::ERROR => 'error',
      RfcLogLevel::CRITICAL => 'critical',
      RfcLogLevel::ALERT => 'alert',
      RfcLogLevel::EMERGENCY => 'emergency',
    ];

    foreach ($context as $key => $value) {
      if (ctype_alpha($key[0]) && !str_contains($message, $key)) {
        unset($context[$key]);
      }
    }

    $level = $map[$level] ?? $level;
    static::$logs[$level][] = (string) new FormattableMarkup($message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function emergency(string|\Stringable $message, array $context = []): void {
    $this->log('emergency', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function alert(string|\Stringable $message, array $context = []): void {
    $this->log('alert', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function critical(string|\Stringable $message, array $context = []): void {
    $this->log('critical', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function error(string|\Stringable $message, array $context = []): void {
    $this->log('error', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function warning(string|\Stringable $message, array $context = []): void {
    $this->log('warning', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function notice(string|\Stringable $message, array $context = []): void {
    $this->log('notice', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function info(string|\Stringable $message, array $context = []): void {
    $this->log('info', $message, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function debug(string|\Stringable $message, array $context = []): void {
    $this->log('debug', $message, $context);
  }

  /**
   * Registers the test logger to the container.
   *
   * @param \Drupal\Core\DependencyInjection\ContainerBuilder $container
   *   The ContainerBuilder to register the test logger to.
   */
  public static function register(ContainerBuilder $container): void {
    $container->register(__CLASS__)->addTag('logger');
  }

}
