<?php

namespace Drupal\backup_migrate\Core\Filter;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Plugin\PluginCallerInterface;
use Drupal\backup_migrate\Core\Plugin\PluginCallerTrait;
use Drupal\backup_migrate\Core\Service\MailerInterface;
use Drupal\backup_migrate\Core\Translation\TranslatableTrait;
use Drupal\backup_migrate\Core\Service\StashLogger;
use Drupal\backup_migrate\Core\Service\TeeLogger;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Notifies by email when a backup succeeds or fails.
 *
 * @package Drupal\backup_migrate\Core\Filter
 */
class Notify extends PluginBase implements PluginCallerInterface {
  use PluginCallerTrait;
  use TranslatableTrait;

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Service\StashLogger The logstash
   */
  protected $logstash;

  /**
   * Constructs a Notify object.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $configFactory
   *   The config factory.
   * @param \Drupal\backup_migrate\Core\Service\MailerInterface|null $mailer
   *   The mailer.
   */
  public function __construct(
    $init = [],
    protected readonly ?ConfigFactoryInterface $configFactory = NULL,
    protected readonly ?MailerInterface $mailer = NULL,
  ) {
    parent::__construct($init);
  }

  /**
   * {@inheritdoc}
   */
  public function configSchema(array $params = []) {
    $schema = [];
    // Backup configuration.
    if ($params['operation'] == 'backup') {
      $schema['groups']['notify'] = [
        'title' => 'Email Settings',
      ];

      $schema['fields']['notify_success_enable'] = [
        'group' => 'notify',
        'type' => 'boolean',
        'title' => 'Send an email if backup succeeds',
      ];
      $schema['fields']['notify_success_email'] = [
        'group' => 'notify',
        'type' => 'text',
        'title' => 'Email Address for Success Notices',
        'description' => 'The email added to send a notification about backup.',
      ];

      $schema['fields']['notify_failure_enable'] = [
        'group' => 'notify',
        'type' => 'boolean',
        'title' => 'Send an email if backup fails',
      ];
      $schema['fields']['notify_failure_email'] = [
        'group' => 'notify',
        'type' => 'text',
        'title' => 'Email Address for Failure Notices',
        'description' => 'The email added to send a notification about backup.',
      ];
    }

    return $schema;
  }

  /**
   * Get the default values for the plugin.
   *
   * @return \Drupal\backup_migrate\Core\Config\Config
   *   The default plugin configuration.
   */
  public function configDefaults() {
    $default_email = $this->siteConfig('mail');

    return new Config([
      'notify_success_enable' => FALSE,
      'notify_success_email' => $default_email,
      'notify_failure_enable' => FALSE,
      'notify_failure_email' => $default_email,
    ]);
  }

  /**
   * Add a weight so that our before* operations run before any others.
   *
   * Primarily to ensure this one runs before other plugins have a chance to
   * write any log entries.
   *
   * @return array
   *   A render or configuration array.
   */
  public function supportedOps() {
    return [
      'beforeBackup' => ['weight' => -100000],
      'beforeRestore' => ['weight' => -100000],
    ];
  }

  /**
   * Handles the before backup operation.
   */
  public function beforeBackup() {
    $this->addLogger();
  }

  /**
   * Handles the before restore operation.
   */
  public function beforeRestore(BackupFileReadableInterface $file) {
    $this->addLogger();
    return $file;
  }

  /**
   * Call notification function if backup was succeed.
   */
  public function backupSuccess() {
    $site_name = (string) $this->siteConfig('name');
    $body = t('Site backup succeeded @site', ['@site' => $site_name]);
    $this->logger()->info($body);
    if ($this->config->get('notify_success_enable')) {
      $subject = 'Backup finished successfully';
      $recipient = $this->config->get('notify_success_email');
      $this->sendNotification('backup_success', $subject, $body, $recipient);
    }
  }

  /**
   * Call notification function if backup was failed.
   */
  public function backupFailure(\Exception $e) {
    $site_name = (string) $this->siteConfig('name');
    $body = t('Site backup failed @site', ['@site' => $site_name]);
    $this->logger()->info($body);
    if ($this->config->get('notify_failure_enable')) {
      $subject = $this->t('Backup finished with failure');
      $body = $this->t('Site backup failed @site', ['@site' => $site_name]) . "\n";
      $body .= $this->t('Exception Message: @exception', [
        '@exception' => $e,
      ]);
      $recipient = $this->config->get('notify_success_email');
      $this->sendNotification('backup_failure', $subject, $body, $recipient);
    }
  }

  /**
   * Handles the restore succeed operation.
   */
  public function restoreSucceed() {
  }

  /**
   * Handles the restore fail operation.
   */
  public function restoreFail() {
  }

  /**
   * Handles the send notification operation.
   *
   * @param mixed $key
   *   The notification key.
   * @param mixed $subject
   *   The subject.
   * @param mixed $body
   *   The body.
   * @param mixed $recipient
   *   The recipient.
   */
  protected function sendNotification($key, $subject, $body, $recipient) {
    if ($this->mailer) {
      $this->mailer->send($key, $recipient, $subject, $body);
    }
  }

  /**
   * Gets a system.site configuration value.
   *
   * @param string $key
   *   The config key.
   *
   * @return mixed
   *   The configured value, or NULL when config is unavailable.
   */
  protected function siteConfig($key) {
    return $this->configFactory ? $this->configFactory->get('system.site')->get($key) : NULL;
  }

  /**
   * Gets the configured logger.
   *
   * @return \Psr\Log\LoggerInterface
   *   The plugin logger.
   */
  protected function logger(): LoggerInterface {
    $logger = $this->plugins()->services()->get('Logger');
    if (!$logger instanceof LoggerInterface) {
      throw new \UnexpectedValueException('The Logger service must implement LoggerInterface.');
    }

    return $logger;
  }

  /**
   * Add the stash logger to the service locator to capture all logged messages.
   */
  protected function addLogger() {
    $services = $this->plugins()->services();

    // Get the current logger.
    $logger = $services->get('Logger');

    // Create a new stash logger to save messages.
    $this->logstash = new StashLogger();

    // Add a tee to send logs to both the regular logger and our stash.
    $services->add('Logger', new TeeLogger([$logger, $this->logstash]));

    // Add the services back into the plugin manager to re-inject existing
    // plugins.
    $this->plugins()->setServiceManager($services);
  }

  // @todo Add a tee to the logger to capture all messages.
  // @todo Implement backup/restore fail/succeed ops and send a notification.
}
