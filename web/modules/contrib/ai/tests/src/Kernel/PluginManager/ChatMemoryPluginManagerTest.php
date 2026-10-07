<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\PluginManager;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;

/**
 * Tests the ChatMemory plugin manager and the test plugin.
 *
 * @coversDefaultClass \Drupal\ai\PluginManager\ChatMemoryPluginManager
 *
 * @group ai
 */
class ChatMemoryPluginManagerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
    'user',
  ];

  /**
   * Tests that the plugins are discovered.
   */
  public function testDiscovery(): void {
    $definitions = $this->container->get('plugin.manager.ai.chat_memory')->getDefinitions();
    $this->assertArrayHasKey('state_memory', $definitions);
  }

  /**
   * Tests the full memory lifecycle with the state test plugin.
   */
  public function testMemoryLifecycle(): void {
    /** @var \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface $plugin */
    $plugin = $this->container->get('plugin.manager.ai.chat_memory')->createInstance('state_memory');
    $this->assertInstanceOf(ChatMemoryInterface::class, $plugin);

    // Thread creation.
    $thread_id = $plugin->createThreadId();
    $this->assertNotEmpty($thread_id);
    $this->assertNotEquals($thread_id, $plugin->createThreadId());
    $this->assertFalse($plugin->hasThread($thread_id));
    $this->assertSame([], $plugin->loadMessages($thread_id));

    // Appending messages.
    $plugin->appendMessages($thread_id, [
      new ChatMessage('user', 'Hello'),
      new ChatMessage('assistant', 'Hi there! How can I assist you today?'),
    ]);
    $this->assertTrue($plugin->hasThread($thread_id));
    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(2, $messages);
    $this->assertSame('user', $messages[0]->getRole());
    $this->assertSame('Hello', $messages[0]->getText());
    $this->assertSame('assistant', $messages[1]->getRole());

    // Appending keeps existing messages.
    $plugin->appendMessages($thread_id, [
      new ChatMessage('user', 'Tell me a joke.'),
    ]);
    $this->assertCount(3, $plugin->loadMessages($thread_id));

    // Saving overwrites all messages.
    $plugin->saveMessages($thread_id, [
      new ChatMessage('user', 'Fresh start'),
    ]);
    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(1, $messages);
    $this->assertSame('Fresh start', $messages[0]->getText());

    // Clearing keeps the thread, deleting removes it.
    $plugin->clearMessages($thread_id);
    $this->assertSame([], $plugin->loadMessages($thread_id));
    $this->assertTrue($plugin->hasThread($thread_id));
    $plugin->deleteThread($thread_id);
    $this->assertFalse($plugin->hasThread($thread_id));

    // Threads do not leak into each other.
    $other_thread_id = $plugin->createThreadId();
    $plugin->appendMessages($other_thread_id, [new ChatMessage('user', 'Other thread')]);
    $this->assertSame([], $plugin->loadMessages($thread_id));
  }

  /**
   * Tests that appending validates the message type.
   */
  public function testAppendValidation(): void {
    /** @var \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface $plugin */
    $plugin = $this->container->get('plugin.manager.ai.chat_memory')->createInstance('state_memory');
    $this->expectException(\InvalidArgumentException::class);
    $plugin->appendMessages($plugin->createThreadId(), ['not a message']);
  }

}
