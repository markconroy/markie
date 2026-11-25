<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Kernel;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the runner's thread pool once every slot is in use.
 *
 * @coversDefaultClass \Drupal\ai_assistant_api\AiAssistantApiRunner
 *
 * @group ai_assistant_api
 */
class AiAssistantApiRunnerThreadPoolTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
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
   * The assistant under test.
   */
  protected AiAssistant $assistant;

  /**
   * The thread key prefix for the current user.
   */
  protected string $prefix;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['ai_assistant_api']);
    $account = $this->setUpCurrentUser();
    $this->prefix = 'assistant_thread_' . $account->id() . '_';

    $this->assistant = AiAssistant::create([
      'id' => 'test_assistant',
      'label' => 'Test assistant',
      'description' => '',
      'allow_history' => 'private_tempstore_pool',
      'chat_memory_settings' => ['max_messages' => 0, 'expiry' => 604800],
      'system_role' => '',
      'preprompt_instructions' => '',
      'assistant_message' => '',
      'error_message' => '',
      'llm_provider' => '',
      'llm_model' => '',
      'llm_configuration' => [],
      'specific_error_messages' => [],
    ]);
    $this->assistant->save();

    // Fill all 11 slots, slot 0 most recently used and slot 4 least.
    $memory = $this->assistant->getChatMemory();
    $now = time();
    for ($i = 0; $i <= 10; $i++) {
      $last_used = $i === 4 ? $now - 1000 : $now - $i;
      $memory->appendMessages($this->prefix . $i, [
        new ChatMessage('user', 'Question ' . $i, [], $last_used),
      ]);
    }
  }

  /**
   * Gets a fresh runner, as a new request would.
   */
  protected function runner() {
    $this->container->set('ai_assistant_api.runner', NULL);
    $runner = $this->container->get('ai_assistant_api.runner');
    $runner->setAssistant($this->assistant);
    return $runner;
  }

  /**
   * Tests that setting the assistant does not evict a thread.
   *
   * @covers ::setAssistant
   */
  public function testSetAssistantDoesNotEvict(): void {
    $runner = $this->runner();
    $runner->setThreadsKey($this->prefix . '0');

    for ($i = 0; $i <= 10; $i++) {
      $this->assertTrue($this->assistant->getChatMemory()->hasThread($this->prefix . $i));
    }
    $this->assertCount(1, $runner->getMessageHistory());
  }

  /**
   * Tests that a full pool evicts the least recently used thread.
   *
   * @covers ::generateUniqueKey
   */
  public function testFullPoolEvictsLeastRecentlyUsed(): void {
    $runner = $this->runner();
    $runner->getTempStore()->set($this->prefix . '4', ['output_contexts' => ['stale']]);

    $this->assertSame($this->prefix . '4', $runner->getThreadsKey());
    $memory = $this->assistant->getChatMemory();
    $this->assertFalse($memory->hasThread($this->prefix . '4'));
    $this->assertTrue($memory->hasThread($this->prefix . '0'));
    $this->assertSame([], $runner->getOutputContexts());
  }

}
