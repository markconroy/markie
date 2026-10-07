<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_assistant_api\Plugin\ChatMemory\PrivateTempStoreChatMemory;
use Drupal\ai_assistant_api\Plugin\ChatMemory\PrivateTempStorePoolChatMemory;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_assistant_api_post_update_convert_allow_history().
 *
 * @group ai_assistant_api
 */
#[RunTestsInSeparateProcesses]
class ConvertAllowHistoryPostUpdateTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'ai',
    'ai_assistant_api',
  ];

  /**
   * The ID of the AI Assistant used across the tests.
   */
  protected const ASSISTANT_ID = 'test_assistant';

  /**
   * Tests migration of the old "one thread per session" setting.
   *
   * Old format: allow_history is the enum value 'session_one_thread' and
   * the history length lives in the top level history_context_length field.
   *
   * New format: allow_history holds the chat memory plugin ID
   * ('private_tempstore') and chat_memory_settings holds its configuration.
   */
  public function testPostUpdateHookConvertsSessionOneThread(): void {
    $this->writeLegacyAssistant('session_one_thread', 5);

    $this->runConvertAllowHistoryPostUpdate();

    $assistant = $this->reloadAssistant();
    $this->assertSame('private_tempstore', $assistant->get('allow_history'));
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 5,
    ], $assistant->get('chat_memory_settings'));

    $chatMemory = $assistant->getChatMemory();
    $this->assertInstanceOf(PrivateTempStoreChatMemory::class, $chatMemory);
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 5,
    ], $chatMemory->getConfiguration());

    $this->assertConfigSchemaByName('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
  }

  /**
   * Tests migration of the old "one thread per user" (pool) setting.
   */
  public function testPostUpdateHookConvertsSession(): void {
    $this->writeLegacyAssistant('session', 10);

    $this->runConvertAllowHistoryPostUpdate();

    $assistant = $this->reloadAssistant();
    $this->assertSame('private_tempstore_pool', $assistant->get('allow_history'));
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 10,
    ], $assistant->get('chat_memory_settings'));

    $chatMemory = $assistant->getChatMemory();
    $this->assertInstanceOf(PrivateTempStorePoolChatMemory::class, $chatMemory);

    $this->assertConfigSchemaByName('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
  }

  /**
   * Tests migration when history was disabled entirely.
   *
   * There is no chat memory plugin to configure, so chat_memory_settings
   * must end up empty and allow_history must end up as an empty string
   * rather than an unmapped legacy value.
   */
  public function testPostUpdateHookConvertsDisabledHistory(): void {
    $this->writeLegacyAssistant('', 0);

    $this->runConvertAllowHistoryPostUpdate();

    $assistant = $this->reloadAssistant();
    $this->assertSame('', $assistant->get('allow_history'));
    $this->assertSame([], $assistant->get('chat_memory_settings'));
    $this->assertNull($assistant->getChatMemory());

    $this->assertConfigSchemaByName('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
  }

  /**
   * Writes an ai_assistant config in the legacy, pre chat-memory shape.
   *
   * Creates and saves a normal assistant through the entity API first, so
   * every field except the two under test is guaranteed to be in a valid,
   * current shape. The config is then rewritten directly in storage to
   * look like a pre-update site: chat_memory_settings did not exist yet,
   * allow_history was the raw enum value, and history_context_length was
   * still a top level field.
   *
   * The initial create()/save() uses a non-empty allow_history
   * ('private_tempstore') regardless of what the legacy value under test
   * is, purely so the entity starts out in a state that satisfies the
   * current entity/schema before it's rewritten to the legacy shape below.
   *
   * @param string $allow_history
   *   The legacy allow_history value ('', 'session' or 'session_one_thread').
   * @param int $history_context_length
   *   The legacy history_context_length value.
   */
  protected function writeLegacyAssistant(string $allow_history, int $history_context_length): void {
    AiAssistant::create([
      'id' => self::ASSISTANT_ID,
      'label' => 'Test Assistant',
      'description' => '',
      'instructions' => '',
      'allow_history' => 'private_tempstore',
      'chat_memory_settings' => ['expiry' => 86400, 'max_messages' => 0],
      'error_message' => 'An error occurred.',
      'llm_provider' => 'echoai',
      'llm_model' => 'gpt-test',
      'llm_configuration' => [],
      'specific_error_messages' => [],
    ])->save();

    $storage = $this->container->get('config.storage');
    $name = 'ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID;
    $data = $storage->read($name);
    unset($data['chat_memory_settings']);
    $data['allow_history'] = $allow_history;
    $data['history_context_length'] = $history_context_length;
    $storage->write($name, $data);

    $this->container->get('entity_type.manager')->getStorage('ai_assistant')->resetCache([self::ASSISTANT_ID]);
  }

  /**
   * Loads a fresh copy of the test assistant.
   *
   * @return \Drupal\ai_assistant_api\Entity\AiAssistant
   *   The assistant.
   */
  protected function reloadAssistant(): AiAssistant {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_assistant');
    $storage->resetCache([self::ASSISTANT_ID]);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
    $assistant = $storage->load(self::ASSISTANT_ID);
    return $assistant;
  }

  /**
   * Runs the ai_assistant_api_post_update_convert_allow_history() post update.
   */
  protected function runConvertAllowHistoryPostUpdate(): void {
    $this->container->get('module_handler')->loadInclude('ai_assistant_api', 'php', 'ai_assistant_api.post_update');
    $sandbox = [];
    ai_assistant_api_post_update_convert_allow_history($sandbox);
  }

}
