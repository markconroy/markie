<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel\Cron;

use Drupal\ai_logging\AiLogInterface;
use Drupal\ai_logging\Cron\AiLogPruning;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI log pruning service.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Cron\AiLogPruning
 */
#[RunTestsInSeparateProcesses]
class AiLogPruningTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'views',
    'ai',
    'ai_logging',
  ];

  /**
   * The service under test.
   */
  protected AiLogPruning $pruning;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);

    $this->pruning = new AiLogPruning(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('logger.factory'),
    );
  }

  /**
   * Age-based pruning deletes old logs and does not error on the survivors.
   *
   * Regression test: pruneLogs() used to re-load and delete the same IDs a
   * second time after already deleting them, which errors on load()
   * returning NULL.
   *
   * @covers ::pruneLogs
   */
  public function testPruneByAgeDeletesOldLogsWithoutError(): void {
    $old = $this->createTestLog(time() - (40 * 86400));
    $recent = $this->createTestLog(time() - (5 * 86400));

    $this->setSettings(max_age: 30);
    $this->pruning->pruneLogs();

    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $this->assertNull($storage->load($old->id()));
    $this->assertNotNull($storage->load($recent->id()));
  }

  /**
   * Age-based pruning is a no-op when disabled.
   *
   * @covers ::pruneLogs
   */
  public function testPruneByAgeDisabled(): void {
    $log = $this->createTestLog(time() - (999 * 86400));

    $this->setSettings(max_age: 0);
    $this->pruning->pruneLogs();

    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $this->assertNotNull($storage->load($log->id()));
  }

  /**
   * Count-based pruning keeps only the most recent N logs.
   *
   * @covers ::pruneLogs
   */
  public function testPruneByCountKeepsMostRecent(): void {
    $oldest = $this->createTestLog(time() - 300);
    $middle = $this->createTestLog(time() - 200);
    $newest = $this->createTestLog(time() - 100);

    $this->setSettings(max_messages: 2);
    $this->pruning->pruneLogs();

    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $this->assertNull($storage->load($oldest->id()));
    $this->assertNotNull($storage->load($middle->id()));
    $this->assertNotNull($storage->load($newest->id()));
  }

  /**
   * Count-based pruning is a no-op when disabled.
   *
   * @covers ::pruneLogs
   */
  public function testPruneByCountDisabled(): void {
    $log = $this->createTestLog(time());

    $this->setSettings(max_messages: 0);
    $this->pruning->pruneLogs();

    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $this->assertNotNull($storage->load($log->id()));
  }

  /**
   * Sets the pruning-related config values.
   */
  protected function setSettings(int $max_messages = 0, int $max_age = 0): void {
    $this->config('ai_logging.settings')
      ->set('prompt_logging_max_messages', $max_messages)
      ->set('prompt_logging_max_age', $max_age)
      ->save();
  }

  /**
   * Creates a test ai_log entity with a given creation time.
   */
  protected function createTestLog(int $created): AiLogInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    /** @var \Drupal\ai_logging\AiLogInterface $log */
    $log = $storage->create([
      'bundle' => 'generic',
      'operation_type' => 'chat',
      'provider' => 'test',
      'model' => 'test-model',
      'created' => $created,
    ]);
    $log->save();
    return $log;
  }

}
