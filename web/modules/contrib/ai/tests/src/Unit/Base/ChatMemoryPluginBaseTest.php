<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Base;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Base\ChatMemoryPluginBase;
use Drupal\ai\OperationType\Chat\ChatMessage;

/**
 * Tests the ChatMemory plugin base class.
 *
 * @coversDefaultClass \Drupal\ai\Base\ChatMemoryPluginBase
 *
 * @group ai
 */
class ChatMemoryPluginBaseTest extends UnitTestCase {

  /**
   * Creates a minimal in-memory plugin from the base class.
   */
  protected function createPlugin(): ChatMemoryPluginBase {
    return new class([], 'in_memory', []) extends ChatMemoryPluginBase {

      /**
       * The in-memory storage, keyed by thread id.
       *
       * @var array<string, \Drupal\ai\OperationType\Chat\ChatMessage[]>
       */
      public array $store = [];

      /**
       * The thread counter.
       *
       * @var int
       */
      protected int $counter = 0;

      /**
       * {@inheritdoc}
       */
      public function createThreadId(): string {
        return 'thread-' . ++$this->counter;
      }

      /**
       * {@inheritdoc}
       */
      public function hasThread(string $thread_id): bool {
        return isset($this->store[$thread_id]);
      }

      /**
       * {@inheritdoc}
       */
      public function loadMessages(string $thread_id): array {
        return $this->store[$thread_id] ?? [];
      }

      /**
       * {@inheritdoc}
       */
      public function saveMessages(string $thread_id, array $messages): void {
        $this->store[$thread_id] = $messages;
      }

      /**
       * {@inheritdoc}
       */
      public function deleteThread(string $thread_id): void {
        unset($this->store[$thread_id]);
      }

    };
  }

  /**
   * Tests that appendMessages() merges onto the existing history in order.
   *
   * @covers ::appendMessages
   */
  public function testAppendMessagesMergesInOrder(): void {
    $plugin = $this->createPlugin();
    $thread_id = $plugin->createThreadId();

    $plugin->appendMessages($thread_id, [new ChatMessage('user', 'one')]);
    $plugin->appendMessages($thread_id, [
      new ChatMessage('assistant', 'two'),
      new ChatMessage('user', 'three'),
    ]);

    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(3, $messages);
    $this->assertSame(['one', 'two', 'three'], array_map(fn (ChatMessage $m) => $m->getText(), $messages));
    $this->assertSame(['user', 'assistant', 'user'], array_map(fn (ChatMessage $m) => $m->getRole(), $messages));
  }

  /**
   * Tests that appendMessages() rejects anything but ChatMessage objects.
   *
   * @covers ::appendMessages
   */
  public function testAppendMessagesValidatesType(): void {
    $plugin = $this->createPlugin();
    $this->expectException(\InvalidArgumentException::class);
    $plugin->appendMessages($plugin->createThreadId(), ['not a chat message']);
  }

  /**
   * Tests that clearMessages() empties the thread but keeps it valid.
   *
   * @covers ::clearMessages
   */
  public function testClearMessagesKeepsThread(): void {
    $plugin = $this->createPlugin();
    $thread_id = $plugin->createThreadId();
    $plugin->appendMessages($thread_id, [new ChatMessage('user', 'to be cleared')]);

    $plugin->clearMessages($thread_id);
    $this->assertSame([], $plugin->loadMessages($thread_id));
    $this->assertTrue($plugin->hasThread($thread_id));
  }

  /**
   * Tests the configuration boilerplate.
   *
   * @covers ::getConfiguration
   * @covers ::setConfiguration
   * @covers ::defaultConfiguration
   */
  public function testConfiguration(): void {
    $plugin = $this->createPlugin();
    $this->assertSame([], $plugin->defaultConfiguration());

    $plugin->setConfiguration(['expiry' => 60]);
    $this->assertSame(['expiry' => 60], $plugin->getConfiguration());
  }

  /**
   * Tests that explicit configuration is merged onto the defaults.
   *
   * The base class implements ConfigurableInterface itself rather than
   * inheriting it from core, so the merge-onto-defaults semantics are
   * asserted here directly.
   *
   * @covers ::__construct
   * @covers ::setConfiguration
   */
  public function testConfigurationMergesOntoDefaults(): void {
    $plugin = $this->createPluginWithDefaults(['expiry' => 30]);

    $this->assertSame([
      'expiry' => 30,
      'max_messages' => 0,
      'nested' => ['a' => 1, 'b' => 2],
    ], $plugin->getConfiguration());

    $plugin->setConfiguration(['nested' => ['b' => 3]]);
    $this->assertSame([
      'expiry' => 86400,
      'max_messages' => 0,
      'nested' => ['a' => 1, 'b' => 3],
    ], $plugin->getConfiguration());
  }

  /**
   * Creates a plugin from the base class that declares default configuration.
   *
   * @param array $configuration
   *   The explicit plugin configuration.
   */
  protected function createPluginWithDefaults(array $configuration): ChatMemoryPluginBase {
    return new class($configuration, 'with_defaults', []) extends ChatMemoryPluginBase {

      /**
       * {@inheritdoc}
       */
      public function defaultConfiguration() {
        return [
          'expiry' => 86400,
          'max_messages' => 0,
          'nested' => ['a' => 1, 'b' => 2],
        ];
      }

      /**
       * {@inheritdoc}
       */
      public function createThreadId(): string {
        return 'thread';
      }

      /**
       * {@inheritdoc}
       */
      public function hasThread(string $thread_id): bool {
        return FALSE;
      }

      /**
       * {@inheritdoc}
       */
      public function loadMessages(string $thread_id): array {
        return [];
      }

      /**
       * {@inheritdoc}
       */
      public function saveMessages(string $thread_id, array $messages): void {}

      /**
       * {@inheritdoc}
       */
      public function deleteThread(string $thread_id): void {}

    };
  }

}
