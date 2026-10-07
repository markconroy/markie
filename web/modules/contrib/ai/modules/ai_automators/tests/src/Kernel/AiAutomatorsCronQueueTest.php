<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\ai_automators\Hook\AiAutomatorsHooks;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Queue\QueueWorkerInterface;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the ai_automators cron queue processing.
 *
 * @group ai_automators
 *
 * @see \Drupal\ai_automators\Hook\AiAutomatorsHooks::cron()
 * @see https://www.drupal.org/i/3575190
 */
final class AiAutomatorsCronQueueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var array<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_automators']);
  }

  /**
   * Builds the cron hook with a stub queue worker.
   *
   * @param \Drupal\Core\Queue\QueueWorkerInterface $worker
   *   The stub worker to run for each item.
   *
   * @return \Drupal\ai_automators\Hook\AiAutomatorsHooks
   *   The hook handler wired to the stub worker.
   */
  private function cronHook(QueueWorkerInterface $worker): AiAutomatorsHooks {
    $manager = $this->createMock(QueueWorkerManagerInterface::class);
    $manager->method('createInstance')->willReturn($worker);
    return new AiAutomatorsHooks(
      $this->container->get('config.factory'),
      $this->container->get('queue'),
      $manager,
      $this->container->get('logger.factory'),
    );
  }

  /**
   * The AI Automator field-modifier queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  private function queue() {
    return $this->container->get('queue')->get('ai_automator_field_modifier');
  }

  /**
   * Sets the configured queue items per cron run.
   */
  private function setLimit(int $limit): void {
    $this->config('ai_automators.settings')->set('queue_cron_items', $limit)->save();
  }

  /**
   * A worker that succeeds and records how many items it processed.
   */
  private function countingWorker(): QueueWorkerInterface {
    return new class([], 'counting', []) extends QueueWorkerBase {

      /**
       * The number of processed items.
       *
       * @var int
       */
      public int $count = 0;

      /**
       * {@inheritdoc}
       */
      public function processItem($data) {
        $this->count++;
      }

    };
  }

  /**
   * Only queue_cron_items items are processed per cron run.
   */
  public function testLimitIsRespected(): void {
    $queue = $this->queue();
    for ($i = 0; $i < 5; $i++) {
      $queue->createItem(['n' => $i]);
    }
    $this->setLimit(2);

    $worker = $this->countingWorker();
    $this->cronHook($worker)->cron();

    $this->assertSame(2, $worker->count, 'Exactly the configured number of items is processed.');
    $this->assertSame(3, $queue->numberOfItems(), 'The remaining items stay queued.');
  }

  /**
   * A limit of 0 drains the whole queue in one run.
   */
  public function testZeroLimitDrainsQueue(): void {
    $queue = $this->queue();
    for ($i = 0; $i < 5; $i++) {
      $queue->createItem(['n' => $i]);
    }
    $this->setLimit(0);

    $worker = $this->countingWorker();
    $this->cronHook($worker)->cron();

    $this->assertSame(5, $worker->count);
    $this->assertSame(0, $queue->numberOfItems(), 'All pending items are processed.');
  }

  /**
   * A permanently failing item is dropped after MAX_ATTEMPTS, not looped.
   */
  public function testPoisonItemIsDroppedAndDoesNotLoop(): void {
    $queue = $this->queue();
    $queue->createItem(['entity_id' => 1]);
    // 0 limit would loop forever on a released item without the attempts cap.
    $this->setLimit(0);

    $worker = new class([], 'failing', []) extends QueueWorkerBase {

      /**
       * The number of processing attempts.
       *
       * @var int
       */
      public int $count = 0;

      /**
       * {@inheritdoc}
       */
      public function processItem($data) {
        $this->count++;
        throw new \Exception('always fails');
      }

    };
    $this->cronHook($worker)->cron();

    $this->assertSame(
      AiAutomatorsHooks::MAX_ATTEMPTS,
      $worker->count,
      'The item is retried up to MAX_ATTEMPTS and no further.',
    );
    $this->assertSame(0, $queue->numberOfItems(), 'The poison item is dropped, not requeued forever.');
  }

  /**
   * A \Throwable (not just \Exception) is caught and the item is requeued.
   */
  public function testThrowableIsCaughtAndItemRequeued(): void {
    $queue = $this->queue();
    $queue->createItem(['entity_id' => 1]);
    $this->setLimit(1);

    $worker = new class([], 'throwing', []) extends QueueWorkerBase {

      /**
       * {@inheritdoc}
       */
      public function processItem($data) {
        // A \TypeError is a \Throwable but not an \Exception.
        throw new \TypeError('fatal-style error');
      }

    };
    // Must not propagate the Throwable out of cron().
    $this->cronHook($worker)->cron();

    $this->assertSame(1, $queue->numberOfItems(), 'The failed item is requeued for a later run.');
    $item = $queue->claimItem();
    $this->assertSame(1, $item->data['_attempts'], 'The retry attempt counter is incremented.');
  }

}
