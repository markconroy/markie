<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Kernel\EventSubscriber;

use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests logging of streamed AI responses.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\EventSubscriber\LogPostRequestEventSubscriber
 */
#[RunTestsInSeparateProcesses]
class LogPostRequestEventSubscriberTest extends KernelTestBase {

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

    $this->config('ai_logging.settings')
      ->set('prompt_logging', TRUE)
      ->set('prompt_logging_output', TRUE)
      ->save();
  }

  /**
   * Tests that a streamed response updates the entry created for it.
   *
   * When a chat response is streamed, the subscriber creates a placeholder
   * log entry as soon as PostGenerateResponseEvent fires (before any chunk
   * has been consumed), then must update that same entry once the stream
   * finishes and PostStreamingResponseEvent fires with the final output. It
   * must not leave the placeholder in place, nor create a second entry for
   * the completed stream.
   *
   * @covers ::logPostRequest
   * @covers ::logPostStream
   */
  public function testStreamedResponseUpdatesSameLogEntry(): void {
    $request_thread_id = 'test-thread-id';

    $iterator = new class(new \ArrayIterator(['Hello ', 'world'])) extends StreamedChatMessageIterator {

      /**
       * {@inheritdoc}
       */
      public function doIterate(): \Generator {
        $index = 0;
        foreach ($this->iterator as $chunk) {
          yield $this->createStreamedChatMessage('assistant', $chunk, [], NULL, ['chunk_' . $index => $chunk]);
          $index++;
        }
      }

    };
    $iterator->setRequestThreadId($request_thread_id);
    $iterator->setProviderId('test_provider');
    $iterator->setModelId('test_model');

    $initial_output = new ChatOutput($iterator, ['status' => 'pending'], []);

    $post_generate_event = new PostGenerateResponseEvent(
      requestThreadId: $request_thread_id,
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $initial_output,
    );

    \Drupal::service('event_dispatcher')->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);

    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $logs = $storage->loadMultiple();
    $this->assertCount(1, $logs, 'A single placeholder log entry is created when streaming starts.');
    $log = reset($logs);
    $log_id = $log->id();
    $this->assertEquals(json_encode(['status' => 'pending']), $log->get('output_text')->value);
    $this->assertNull($log->get('response_text')->value, 'The reply of a stream is not known before it is consumed.');

    // Fully consume the stream, this triggers PostStreamingResponseEvent.
    iterator_to_array($iterator);

    $storage->resetCache();
    $logs_after = $storage->loadMultiple();
    $this->assertCount(1, $logs_after, 'Completing the stream must not create a second log entry.');

    $updated_log = $storage->load($log_id);
    $this->assertNotNull($updated_log);
    $this->assertEquals(
      json_encode(['chunk_0' => 'Hello ', 'chunk_1' => 'world']),
      $updated_log->get('output_text')->value,
      'The placeholder entry is updated in place with the final streamed output.'
    );
    $this->assertSame('Hello world', $updated_log->get('response_text')->value, 'The assembled streamed reply is logged.');
  }

  /**
   * Tests that token usage is captured immediately for non-streamed output.
   *
   * @covers ::logPostRequest
   * @covers ::setTokenUsageFields
   */
  public function testNonStreamedResponseCapturesTokenUsage(): void {
    $output = new ChatOutput(new ChatMessage('assistant', 'Hello world'), ['status' => 'ok'], []);
    $output->setTokenUsage(new TokenUsageDto(input: 12, output: 34, total: 46, reasoning: 5, cached: 2));

    $post_generate_event = new PostGenerateResponseEvent(
      requestThreadId: 'test-thread-id-non-streamed',
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $output,
    );

    \Drupal::service('event_dispatcher')->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);

    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $logs = $storage->loadMultiple();
    $this->assertCount(1, $logs);
    $log = reset($logs);
    $this->assertEquals(12, $log->get('tokens_input')->value);
    $this->assertEquals(34, $log->get('tokens_output')->value);
    $this->assertEquals(46, $log->get('tokens_total')->value);
    $this->assertEquals(5, $log->get('tokens_reasoning')->value);
    $this->assertEquals(2, $log->get('tokens_cached')->value);
  }

  /**
   * Tests that streamed token usage is only captured once the stream ends.
   *
   * Mirrors how real providers work: usage totals are unknown until the
   * final chunk arrives, so the placeholder entry has no token counts until
   * PostStreamingResponseEvent fires.
   *
   * @covers ::logPostRequest
   * @covers ::logPostStream
   * @covers ::setTokenUsageFields
   */
  public function testStreamedResponseCapturesTokenUsageOnCompletion(): void {
    $request_thread_id = 'test-thread-id-tokens';

    $iterator = new class(new \ArrayIterator(['Hello ', 'world'])) extends StreamedChatMessageIterator {

      /**
       * {@inheritdoc}
       */
      public function doIterate(): \Generator {
        foreach ($this->iterator as $chunk) {
          yield $this->createStreamedChatMessage('assistant', $chunk, []);
        }
        // Providers typically report final usage once the stream ends.
        $usage_message = $this->createStreamedChatMessage('assistant', '', []);
        $usage_message->setInputTokenUsage(7);
        $usage_message->setOutputTokenUsage(9);
        $usage_message->setTotalTokenUsage(16);
      }

    };
    $iterator->setRequestThreadId($request_thread_id);
    $iterator->setProviderId('test_provider');
    $iterator->setModelId('test_model');

    $initial_output = new ChatOutput($iterator, [], []);

    $post_generate_event = new PostGenerateResponseEvent(
      requestThreadId: $request_thread_id,
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $initial_output,
    );

    \Drupal::service('event_dispatcher')->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);

    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $logs = $storage->loadMultiple();
    $log = reset($logs);
    $log_id = $log->id();
    $this->assertNull($log->get('tokens_total')->value, 'Token usage is unknown before the stream is consumed.');

    iterator_to_array($iterator);

    $storage->resetCache();
    $updated_log = $storage->load($log_id);
    $this->assertEquals(7, $updated_log->get('tokens_input')->value);
    $this->assertEquals(9, $updated_log->get('tokens_output')->value);
    $this->assertEquals(16, $updated_log->get('tokens_total')->value);
  }

  /**
   * Tests that token usage is captured even with output logging disabled.
   *
   * Before this was fixed, the placeholder log entry's ID was only
   * remembered for later update when "prompt_logging_output" was enabled,
   * so a streamed response's final token usage was silently dropped
   * whenever output logging was off.
   *
   * @covers ::logPostRequest
   * @covers ::logPostStream
   */
  public function testStreamedTokenUsageCapturedWithOutputLoggingDisabled(): void {
    $this->config('ai_logging.settings')->set('prompt_logging_output', FALSE)->save();
    $request_thread_id = 'test-thread-id-tokens-no-output';

    $iterator = new class(new \ArrayIterator(['Hello ', 'world'])) extends StreamedChatMessageIterator {

      /**
       * {@inheritdoc}
       */
      public function doIterate(): \Generator {
        foreach ($this->iterator as $chunk) {
          yield $this->createStreamedChatMessage('assistant', $chunk, []);
        }
        $usage_message = $this->createStreamedChatMessage('assistant', '', []);
        $usage_message->setTotalTokenUsage(16);
      }

    };
    $iterator->setRequestThreadId($request_thread_id);
    $iterator->setProviderId('test_provider');
    $iterator->setModelId('test_model');

    $initial_output = new ChatOutput($iterator, ['status' => 'pending'], []);

    $post_generate_event = new PostGenerateResponseEvent(
      requestThreadId: $request_thread_id,
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $initial_output,
    );

    \Drupal::service('event_dispatcher')->dispatch($post_generate_event, PostGenerateResponseEvent::EVENT_NAME);

    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');
    $logs = $storage->loadMultiple();
    $log = reset($logs);
    $log_id = $log->id();

    iterator_to_array($iterator);

    $storage->resetCache();
    $updated_log = $storage->load($log_id);
    $this->assertEquals(16, $updated_log->get('tokens_total')->value);
    // Output logging stayed disabled throughout, so output_text was never
    // touched, not even with the initial placeholder.
    $this->assertNull($updated_log->get('output_text')->value);
    $this->assertNull($updated_log->get('response_text')->value);
  }

  /**
   * Tests that a request carrying an excluded tag is not logged.
   *
   * @covers ::logPostRequest
   * @covers ::shouldLoggingHappen
   */
  public function testExcludedTagPreventsLogging(): void {
    $this->config('ai_logging.settings')
      ->set('prompt_logging_excluded_tags', 'ai_api_explorer')
      ->save();

    $this->dispatchChatEvent(['chat', 'chat_generation', 'ai_api_explorer']);
    $this->assertSame(0, $this->countLogs(), 'A request with an excluded tag is not logged.');

    $this->dispatchChatEvent(['chat', 'chat_generation']);
    $this->assertSame(1, $this->countLogs(), 'A request without any excluded tag is still logged.');
  }

  /**
   * Tests that an excluded tag wins over a matching allow list entry.
   *
   * @covers ::shouldLoggingHappen
   */
  public function testExcludedTagTakesPrecedenceOverAllowedTag(): void {
    $this->config('ai_logging.settings')
      ->set('prompt_logging_tags', 'chat')
      ->set('prompt_logging_excluded_tags', 'ai_api_explorer')
      ->save();

    $this->dispatchChatEvent(['chat', 'ai_api_explorer']);
    $this->assertSame(0, $this->countLogs(), 'The excluded tag wins even though "chat" is allowed.');

    $this->dispatchChatEvent(['chat']);
    $this->assertSame(1, $this->countLogs(), 'An allowed request without the excluded tag is logged.');

    $this->dispatchChatEvent(['embeddings']);
    $this->assertSame(1, $this->countLogs(), 'The allow list is still enforced for other requests.');
  }

  /**
   * Tests that excluded tags are matched ignoring case and whitespace.
   *
   * @covers ::shouldLoggingHappen
   */
  public function testExcludedTagsAreNormalized(): void {
    $this->config('ai_logging.settings')
      ->set('prompt_logging_excluded_tags', ' AI_API_EXPLORER , ')
      ->save();

    $this->dispatchChatEvent(['chat', 'ai_api_explorer']);
    $this->assertSame(0, $this->countLogs(), 'Case and surrounding whitespace are ignored.');

    $this->dispatchChatEvent(['Chat']);
    $this->assertSame(1, $this->countLogs(), 'A request without the excluded tag is logged.');

    // A trailing separator must not turn into an empty exclusion that would
    // otherwise match nothing or, worse, everything.
    $this->config('ai_logging.settings')
      ->set('prompt_logging_excluded_tags', 'embeddings,')
      ->save();

    $this->dispatchChatEvent(['chat']);
    $this->assertSame(2, $this->countLogs(), 'An empty entry in the excluded tags does not block requests.');
  }

  /**
   * Tests that the default configuration excludes nothing.
   *
   * @covers ::shouldLoggingHappen
   */
  public function testDefaultConfigurationExcludesNothing(): void {
    $this->assertSame('', $this->config('ai_logging.settings')->get('prompt_logging_excluded_tags'));

    $this->dispatchChatEvent(['chat', 'chat_generation', 'ai_api_explorer']);
    $this->assertSame(1, $this->countLogs());
  }

  /**
   * Tests that the normalized text of a plain reply is logged.
   *
   * @covers ::logPostRequest
   * @covers ::getResponseText
   */
  public function testPlainReplyIsLogged(): void {
    $this->dispatchOutputEvent(new ChatOutput(new ChatMessage('assistant', 'Hello world'), ['provider' => 'format'], []));
    $this->assertSame('Hello world', $this->loadOnlyLog()->get('response_text')->value);
  }

  /**
   * Tests that a turn that only calls tools logs no reply.
   *
   * @covers ::getResponseText
   */
  public function testToolOnlyTurnLogsNoReply(): void {
    $this->dispatchOutputEvent(new ChatOutput(new ChatMessage('assistant', ''), [], []));
    $this->assertNull($this->loadOnlyLog()->get('response_text')->value);
  }

  /**
   * Tests that the JSON answer of a structured call is logged as it came.
   *
   * @covers ::getResponseText
   */
  public function testStructuredReplyIsLoggedAsItCame(): void {
    $json = '{"safe":true,"reason":"No injection found."}';
    $this->dispatchOutputEvent(new ChatOutput(new ChatMessage('assistant', $json), [], []));
    $this->assertSame($json, $this->loadOnlyLog()->get('response_text')->value);
  }

  /**
   * Tests that a call that is not chat logs no reply.
   *
   * @covers ::getResponseText
   */
  public function testNonChatCallLogsNoReply(): void {
    $this->dispatchOutputEvent(new EmbeddingsOutput([0.1, 0.2], [], []), 'embeddings');
    $this->assertNull($this->loadOnlyLog()->get('response_text')->value);
  }

  /**
   * Tests that no reply is logged when output logging is off.
   *
   * @covers ::logPostRequest
   */
  public function testReplyNotLoggedWithOutputLoggingDisabled(): void {
    $this->config('ai_logging.settings')->set('prompt_logging_output', FALSE)->save();
    $this->dispatchOutputEvent(new ChatOutput(new ChatMessage('assistant', 'Hello world'), [], []));
    $this->assertNull($this->loadOnlyLog()->get('response_text')->value);
  }

  /**
   * Dispatches a non-streamed event with the given output.
   *
   * @param \Drupal\ai\OperationType\OutputInterface $output
   *   The output.
   * @param string $operation_type
   *   The operation type.
   */
  protected function dispatchOutputEvent(OutputInterface $output, string $operation_type = 'chat'): void {
    $event = new PostGenerateResponseEvent(
      requestThreadId: 'test-thread-output',
      providerId: 'test_provider',
      operationType: $operation_type,
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $output,
    );
    $this->container->get('event_dispatcher')->dispatch($event, PostGenerateResponseEvent::EVENT_NAME);
  }

  /**
   * Loads the only stored log entry.
   *
   * @return \Drupal\ai_logging\AiLogInterface
   *   The log entry.
   */
  protected function loadOnlyLog(): object {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $storage->resetCache();
    $logs = $storage->loadMultiple();
    $this->assertCount(1, $logs);
    return reset($logs);
  }

  /**
   * Dispatches a non-streamed chat event carrying the given request tags.
   *
   * @param string[] $tags
   *   The request tags, as the provider proxy sets them on the provider.
   */
  protected function dispatchChatEvent(array $tags): void {
    $output = new ChatOutput(new ChatMessage('assistant', 'Hello world'), ['status' => 'ok'], []);
    $event = new PostGenerateResponseEvent(
      requestThreadId: 'test-thread-' . md5(implode(',', $tags) . $this->countLogs()),
      providerId: 'test_provider',
      operationType: 'chat',
      configuration: [],
      input: 'Test prompt',
      modelId: 'test_model',
      output: $output,
      tags: $tags,
    );
    $this->container->get('event_dispatcher')->dispatch($event, PostGenerateResponseEvent::EVENT_NAME);
  }

  /**
   * Counts the stored log entries.
   *
   * @return int
   *   The number of AI log entities.
   */
  protected function countLogs(): int {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_log');
    $storage->resetCache();
    return count($storage->loadMultiple());
  }

}
