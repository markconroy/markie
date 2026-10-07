<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Kernel\Plugin\ChatMemory;

use Drupal\ai_assistant_api\Plugin\ChatMemory\PrivateTempStoreChatMemory;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests the private_tempstore chat memory plugin.
 *
 * @coversDefaultClass \Drupal\ai_assistant_api\Plugin\ChatMemory\PrivateTempStoreChatMemory
 *
 * @group ai_assistant_api
 */
class PrivateTempStoreChatMemoryTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_assistant_api',
    'key',
    'file',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->setUpCurrentUser();
  }

  /**
   * Creates a plugin instance with the given configuration.
   */
  protected function createPlugin(array $configuration = []): PrivateTempStoreChatMemory {
    /** @var \Drupal\ai\Plugin\ChatMemory\PrivateTempStoreChatMemory $plugin */
    $plugin = $this->container->get('plugin.manager.ai.chat_memory')->createInstance('private_tempstore', $configuration);
    return $plugin;
  }

  /**
   * Tests the full thread lifecycle.
   */
  public function testThreadLifecycle(): void {
    $plugin = $this->createPlugin();

    $thread_id = $plugin->createThreadId();
    $this->assertFalse($plugin->hasThread($thread_id));
    $this->assertSame([], $plugin->loadMessages($thread_id));

    $plugin->appendMessages($thread_id, [
      new ChatMessage('user', 'Hello'),
      new ChatMessage('assistant', 'Hi there!'),
    ]);
    $this->assertTrue($plugin->hasThread($thread_id));
    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(2, $messages);
    $this->assertSame('user', $messages[0]->getRole());
    $this->assertSame('Hello', $messages[0]->getText());

    $plugin->appendMessages($thread_id, [new ChatMessage('user', 'Another one')]);
    $this->assertCount(3, $plugin->loadMessages($thread_id));

    $plugin->saveMessages($thread_id, [new ChatMessage('user', 'Overwritten')]);
    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(1, $messages);
    $this->assertSame('Overwritten', $messages[0]->getText());

    $plugin->clearMessages($thread_id);
    $this->assertSame([], $plugin->loadMessages($thread_id));
    $this->assertTrue($plugin->hasThread($thread_id));

    $plugin->deleteThread($thread_id);
    $this->assertFalse($plugin->hasThread($thread_id));
  }

  /**
   * Tests that expired threads are not loaded.
   */
  public function testExpiry(): void {
    $plugin = $this->createPlugin();
    // The plugin now stores through a private tempstore, which keeps its
    // collection under a "tempstore.private." prefix and wraps the row in
    // an owner/data/updated object, the same way core's PrivateTempStore
    // does.
    $collection = 'tempstore.private.' . PrivateTempStoreChatMemory::COLLECTION;
    $storage = $this->container->get('keyvalue.expirable')->get($collection);
    $owner = $this->container->get('current_user')->id();
    $thread_data = [
      'created' => time(),
      'updated' => time(),
      'messages' => [(new ChatMessage('user', 'Old message'))->toArray()],
    ];
    $wrap = fn (array $data) => (object) [
      'owner' => $owner,
      'data' => $data,
      'updated' => time(),
    ];

    // A thread whose expiry has already passed is gone.
    $expired_thread_id = $plugin->createThreadId();
    $storage->setWithExpire($owner . ':' . $expired_thread_id, $wrap($thread_data), -100);
    $this->assertFalse($plugin->hasThread($expired_thread_id));
    $this->assertSame([], $plugin->loadMessages($expired_thread_id));

    // A thread within its expiry is still there.
    $thread_id = $plugin->createThreadId();
    $storage->setWithExpire($owner . ':' . $thread_id, $wrap($thread_data), 1000);
    $this->assertTrue($plugin->hasThread($thread_id));
    $this->assertCount(1, $plugin->loadMessages($thread_id));

    // Saving through the plugin applies the configured expiry to the row.
    $plugin->saveMessages($thread_id, [new ChatMessage('user', 'New message')]);
    $expire = $this->container->get('database')
      ->query('SELECT expire FROM {key_value_expire} WHERE collection = :collection AND name = :name', [
        ':collection' => $collection,
        ':name' => $owner . ':' . $thread_id,
      ])->fetchField();
    $this->assertGreaterThan(time() + 86000, (int) $expire);
    $this->assertLessThanOrEqual(time() + 86400, (int) $expire);
  }

  /**
   * Tests that max messages prunes the oldest messages.
   */
  public function testMaxMessages(): void {
    $plugin = $this->createPlugin(['expiry' => 86400, 'max_messages' => 2]);
    $thread_id = $plugin->createThreadId();

    $plugin->appendMessages($thread_id, [
      new ChatMessage('user', 'First question'),
      new ChatMessage('assistant', 'First answer'),
      new ChatMessage('user', 'Second question'),
      new ChatMessage('assistant', 'Second answer'),
      new ChatMessage('user', 'Third question'),
      new ChatMessage('assistant', 'Third answer'),
    ]);
    $messages = $plugin->loadMessages($thread_id);
    $this->assertCount(5, $messages);
    $this->assertSame('First answer', $messages[0]->getText());
    $this->assertSame('Second question', $messages[1]->getText());
  }

  /**
   * Tests appending as an anonymous user after the session is gone.
   *
   * Streamed responses append the assistant message after the response has
   * started, when the session has been saved and can no longer be accessed.
   * The plugin must resolve and cache the anonymous owner key while the
   * session is still available, and never touch the session in the write
   * path afterwards. Regression test for the anonymous streaming bug where
   * the private temp store tried to restart the closed session and the
   * thread was lost.
   */
  public function testAnonymousAppendAfterSessionIsGone(): void {
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    $session = new Session(new MockArraySessionStorage());
    $request_with_session = Request::create('/');
    $request_with_session->setSession($session);
    /** @var \Symfony\Component\HttpFoundation\RequestStack $stack */
    $stack = $this->container->get('request_stack');
    $stack->push($request_with_session);

    // The user message is appended while the session is available. This also
    // primes the cached owner key, like a chatbot calling loadMessages()
    // before the streamed response starts.
    $plugin = $this->createPlugin();
    $thread_id = $plugin->createThreadId();
    $plugin->appendMessages($thread_id, [new ChatMessage('user', 'Sent before streaming')]);

    // Once the response streams, the session is no longer accessible.
    // Reading it would throw a SessionNotFoundException on this request.
    $stack->pop();
    $stack->push(Request::create('/'));
    $plugin->appendMessages($thread_id, [new ChatMessage('assistant', 'Streamed reply')]);
    $stack->pop();

    // On the next request with the same session, the full thread is there,
    // also for a fresh plugin instance without a cached owner.
    $stack->push($request_with_session);
    $messages = $this->createPlugin()->loadMessages($thread_id);
    $stack->pop();
    $this->assertCount(2, $messages);
    $this->assertSame('Sent before streaming', $messages[0]->getText());
    $this->assertSame('Streamed reply', $messages[1]->getText());
  }

  /**
   * Tests that threads are isolated per user.
   */
  public function testUserIsolation(): void {
    $plugin = $this->createPlugin();
    $thread_id = $plugin->createThreadId();
    $plugin->appendMessages($thread_id, [new ChatMessage('user', 'My private message')]);
    $this->assertTrue($plugin->hasThread($thread_id));

    // Another user cannot access the thread, even with the same thread id. A
    // fresh plugin instance is used, since the owner is cached per instance
    // and a different user is always a different request in practice.
    $this->setUpCurrentUser();
    $other_user_plugin = $this->createPlugin();
    $this->assertFalse($other_user_plugin->hasThread($thread_id));
    $this->assertSame([], $other_user_plugin->loadMessages($thread_id));
  }

}
