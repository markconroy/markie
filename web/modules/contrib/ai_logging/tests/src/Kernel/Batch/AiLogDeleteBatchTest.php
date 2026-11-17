<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel\Batch;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_logging\AiLogInterface;
use Drupal\ai_logging\Batch\AiLogDeleteBatch;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deleting AI logs in a batch.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Batch\AiLogDeleteBatch
 */
#[RunTestsInSeparateProcesses]
class AiLogDeleteBatchTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);
  }

  /**
   * Tests that every log is deleted, over several batch requests.
   *
   * @covers ::deleteLogs
   */
  public function testDeleteAllLogs(): void {
    for ($log_index = 0; $log_index < AiLogDeleteBatch::LIMIT + 1; $log_index++) {
      $this->createLog(['tag_' . $log_index]);
    }

    $context = [];
    $requests = $this->runBatch(NULL, $context);

    $this->assertSame(2, $requests);
    $this->assertSame(AiLogDeleteBatch::LIMIT + 1, $context['results']['deleted']);
    $this->assertSame([], $this->loadLogIds());
  }

  /**
   * Tests that only the logs carrying the given tags are deleted.
   *
   * @covers ::deleteLogs
   */
  public function testDeleteLogsByTags(): void {
    $this->createLog(['ai_agents_thread_one']);
    $this->createLog(['ai_assistant_thread_one', 'ai_agents_thread_one']);
    $other_thread = $this->createLog(['ai_agents_thread_two']);
    $untagged = $this->createLog([]);

    $context = [];
    $this->runBatch(['ai_agents_thread_one', 'ai_assistant_thread_one'], $context);

    $this->assertSame(2, $context['results']['deleted'], 'A log carrying two of the tags is deleted once.');
    $this->assertEquals([$other_thread->id(), $untagged->id()], $this->loadLogIds());
  }

  /**
   * Tests that logs created after the batch started are kept.
   *
   * @covers ::deleteLogs
   */
  public function testLogsCreatedDuringBatchAreKept(): void {
    for ($log_index = 0; $log_index < AiLogDeleteBatch::LIMIT + 1; $log_index++) {
      $this->createLog([]);
    }

    $context = [];
    AiLogDeleteBatch::deleteLogs(NULL, $context);
    $this->assertLessThan(1, $context['finished']);
    $new_log = $this->createLog([]);
    AiLogDeleteBatch::deleteLogs(NULL, $context);

    $this->assertEquals(1, $context['finished']);
    $this->assertEquals([$new_log->id()], $this->loadLogIds());
  }

  /**
   * Tests that a batch with nothing to delete finishes at once.
   *
   * @covers ::deleteLogs
   */
  public function testNothingToDelete(): void {
    $this->createLog(['unrelated']);
    $context = [];
    $this->assertSame(1, $this->runBatch(['missing'], $context));
    $this->assertSame(0, $context['results']['deleted']);
    $this->assertCount(1, $this->loadLogIds());
  }

  /**
   * Runs the batch operation until it has finished.
   *
   * @param string[]|null $tags
   *   The tags to pass to the operation.
   * @param array $context
   *   The batch context.
   *
   * @return int
   *   The number of batch requests it took.
   */
  protected function runBatch(?array $tags, array &$context): int {
    $requests = 0;
    do {
      AiLogDeleteBatch::deleteLogs($tags, $context);
      $requests++;
      $this->assertLessThan(10, $requests, 'The batch finishes.');
    } while ($context['finished'] < 1);
    return $requests;
  }

  /**
   * Creates a log.
   *
   * @param string[] $tags
   *   The request tags.
   *
   * @return \Drupal\ai_logging\AiLogInterface
   *   The saved log.
   */
  protected function createLog(array $tags): AiLogInterface {
    $log = $this->container->get('entity_type.manager')->getStorage('ai_log')->create([
      'bundle' => 'generic',
      'operation_type' => 'chat',
      'tags' => $tags,
    ]);
    $log->save();
    return $log;
  }

  /**
   * Loads the IDs of the remaining logs.
   *
   * @return array
   *   The log IDs, in order.
   */
  protected function loadLogIds(): array {
    $ids = $this->container->get('entity_type.manager')->getStorage('ai_log')->getQuery()
      ->accessCheck(FALSE)
      ->sort('id')
      ->execute();
    return array_values($ids);
  }

}
