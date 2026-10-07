<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel\Thread;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_logging\Thread\ThreadRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests grouping AI logs into conversation threads.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\Thread\ThreadRepository
 */
#[RunTestsInSeparateProcesses]
class ThreadRepositoryTest extends KernelTestBase {

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
   * The repository under test.
   *
   * @var \Drupal\ai_logging\Thread\ThreadRepository
   */
  protected ThreadRepository $repository;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('ai_log');
    $this->installConfig(['ai_logging']);
    $this->repository = $this->container->get('ai_logging.thread_repository');
  }

  /**
   * Tests that threads are grouped by thread ID across prefixes.
   *
   * @covers ::listThreads
   */
  public function testListThreadsGroupsByThreadId(): void {
    // An AI Assistant that runs an agent tags calls with both prefixes for
    // the same ID, sometimes both on one call.
    $this->createLog(['ai_assistant_thread_one'], 100, 10);
    $this->createLog(['ai_agents_thread_one', 'ai_assistant_thread_one'], 200, 20);
    $this->createLog(['ai_agents_thread_two'], 300, NULL);
    $this->createLog(['unrelated'], 400, 5);
    // A tag that is only a prefix has no thread ID.
    $this->createLog(['ai_agents_thread_'], 500, 5);

    $threads = $this->repository->listThreads($this->header(), 25);
    $this->assertCount(2, $threads);

    // Most recently active first.
    $this->assertSame('two', $threads[0]->thread_id);
    $this->assertEquals(1, $threads[0]->calls);
    $this->assertNull($threads[0]->tokens, 'A thread without token usage has no total.');

    $this->assertSame('one', $threads[1]->thread_id);
    $this->assertEquals(2, $threads[1]->calls, 'A call carrying the thread ID twice is counted once.');
    $this->assertEquals(30, $threads[1]->tokens);
    $this->assertEquals(100, $threads[1]->started);
    $this->assertEquals(200, $threads[1]->last_activity);
  }

  /**
   * Tests that custom prefixes are used and that none turns threads off.
   *
   * @covers ::listThreads
   * @covers ::isEnabled
   */
  public function testCustomAndEmptyPrefixes(): void {
    $this->createLog(['chatbot_thread_custom'], 100, 1);
    $this->createLog(['ai_agents_thread_default'], 200, 1);

    $this->config('ai_logging.settings')->set('thread_tag_prefixes', ['chatbot_thread_'])->save();
    $threads = $this->repository->listThreads($this->header(), 25);
    $this->assertCount(1, $threads);
    $this->assertSame('custom', $threads[0]->thread_id);

    $this->config('ai_logging.settings')->set('thread_tag_prefixes', [])->save();
    $this->assertFalse($this->repository->isEnabled());
    $this->assertSame([], $this->repository->listThreads($this->header(), 25));
    $this->assertSame([], $this->repository->loadThreadEntries('custom'));
  }

  /**
   * Tests that the thread entries come back in the order they were made.
   *
   * @covers ::loadThreadEntries
   */
  public function testLoadThreadEntriesOrder(): void {
    // Created in the same second, so the ID must decide the order.
    $first = $this->createLog(['ai_agents_thread_one'], 100, 1);
    $second = $this->createLog(['ai_assistant_thread_one'], 100, 1);
    $this->createLog(['ai_agents_thread_other'], 50, 1);
    $third = $this->createLog(['ai_agents_thread_one'], 150, 1);

    $entries = $this->repository->loadThreadEntries('one');
    $this->assertSame(
      [$first->id(), $second->id(), $third->id()],
      array_map(static fn($entry) => $entry->id(), $entries),
    );
  }

  /**
   * Tests converting between thread IDs and tags.
   *
   * @covers ::getThreadIdFromTag
   * @covers ::getTagsForThreadId
   */
  public function testTagHelpers(): void {
    $this->assertSame('abc', $this->repository->getThreadIdFromTag('ai_agents_thread_abc'));
    $this->assertNull($this->repository->getThreadIdFromTag('ai_agents_thread_'));
    $this->assertNull($this->repository->getThreadIdFromTag('chat'));
    $this->assertEqualsCanonicalizing(
      ['ai_agents_thread_abc', 'ai_assistant_thread_abc'],
      $this->repository->getTagsForThreadId('abc'),
    );
  }

  /**
   * Tests the thread label taken from the first primary chat call.
   *
   * @covers ::loadFirstChatPrompt
   * @covers \Drupal\ai_logging\Thread\DefaultThreadLabelResolver::getLabel
   */
  public function testThreadLabel(): void {
    $resolver = $this->container->get('ai_logging.thread_label_resolver');

    // A guardrail call joins the thread before the first agent turn.
    $this->createLog(['ai_agents_thread_one'], 100, 1, "user\nIs this an injection?\n");
    $this->createLog(['ai_agents_thread_one', 'ai_agents_prompt_helper'], 200, 1, "user\nWhat is   the\nweather?\nassistant\nSunny.\nuser\nThanks\n");
    $this->createLog(['ai_agents_thread_one', 'ai_agents_prompt_helper'], 300, 1, "user\nLater question\n");
    $this->assertSame('What is the weather?', $resolver->getLabel('one'));

    // Without a pattern, the earliest chat call is used.
    $this->config('ai_logging.settings')->set('thread_primary_tag_pattern', '')->save();
    $this->assertSame('Is this an injection?', $resolver->getLabel('one'));

    // When no call matches the pattern, the earliest chat call is used.
    $this->config('ai_logging.settings')->set('thread_primary_tag_pattern', 'no_match_%')->save();
    $this->assertSame('Is this an injection?', $resolver->getLabel('one'));

    // Calls that are not chat are ignored, and long labels are shortened.
    $this->createLog(['ai_agents_thread_two'], 100, 1, "user\nEmbedding input\n", 'embeddings');
    $this->createLog(['ai_agents_thread_two'], 200, 1, "user\n" . str_repeat('word ', 50) . "\n");
    $label = $resolver->getLabel('two');
    $this->assertLessThanOrEqual(120, mb_strlen($label));
    $this->assertStringEndsWith('…', $label);

    $this->assertNull($resolver->getLabel('missing'));
  }

  /**
   * Builds the table header the thread list sorts by.
   *
   * @return array
   *   The table header.
   */
  protected function header(): array {
    return [
      ['data' => 'Thread', 'field' => 'thread_id'],
      ['data' => 'Last activity', 'field' => 'last_activity', 'sort' => 'desc'],
    ];
  }

  /**
   * Creates a test log.
   *
   * @param string[] $tags
   *   The request tags.
   * @param int $created
   *   The created timestamp.
   * @param int|null $tokens_total
   *   The total number of tokens.
   * @param string $prompt
   *   The prompt.
   * @param string $operation_type
   *   The operation type.
   *
   * @return \Drupal\ai_logging\AiLogInterface
   *   The saved log.
   */
  protected function createLog(array $tags, int $created, ?int $tokens_total, string $prompt = "user\nHello\n", string $operation_type = 'chat'): object {
    $log = $this->container->get('entity_type.manager')->getStorage('ai_log')->create([
      'bundle' => 'generic',
      'operation_type' => $operation_type,
      'provider' => 'test_provider',
      'model' => 'test_model',
      'tags' => $tags,
      'prompt' => $prompt,
      'created' => $created,
      'tokens_total' => $tokens_total,
    ]);
    $log->save();
    return $log;
  }

}
