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
 * Tests assistants that still carry the pre-ChatMemory settings.
 *
 * Such assistants are normally migrated by
 * ai_assistant_api_post_update_convert_allow_history(), but config exported
 * before that update ran - or imported, or installed by a recipe, afterwards -
 * arrives in the old shape, so the entity has to cope with it when it is
 * loaded.
 *
 * @group ai_assistant_api
 */
#[RunTestsInSeparateProcesses]
class LegacyChatMemorySettingsTest extends KernelTestBase {

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
   * Tests that the legacy one-thread-per-session setting is converted.
   */
  public function testLegacySessionOneThreadUsesThePrivateTempStore(): void {
    // Recipes and older config exports write the length as a string.
    $this->writeLegacyAssistant('session_one_thread', '5');

    $assistant = $this->loadAssistant();

    $this->assertSame('private_tempstore', $assistant->get('allow_history'));
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 5,
    ], $assistant->get('chat_memory_settings'));

    $chat_memory = $assistant->getChatMemory();
    $this->assertInstanceOf(PrivateTempStoreChatMemory::class, $chat_memory);
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 5,
    ], $chat_memory->getConfiguration());
  }

  /**
   * Tests that the legacy one-thread-per-user setting is converted.
   */
  public function testLegacySessionUsesThePrivateTempStorePool(): void {
    $this->writeLegacyAssistant('session', 10);

    $assistant = $this->loadAssistant();

    $this->assertSame('private_tempstore_pool', $assistant->get('allow_history'));
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 10,
    ], $assistant->get('chat_memory_settings'));
    $this->assertInstanceOf(PrivateTempStorePoolChatMemory::class, $assistant->getChatMemory());
  }

  /**
   * Tests that saving an unmigrated assistant stores it in the new shape.
   */
  public function testSavingAnUnmigratedAssistantRewritesTheSettings(): void {
    $this->writeLegacyAssistant('session_one_thread', 5);

    $this->loadAssistant()->save();

    $config = $this->config('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
    $this->assertSame('private_tempstore', $config->get('allow_history'));
    $this->assertSame([
      'expiry' => 604800,
      'max_messages' => 5,
    ], $config->get('chat_memory_settings'));
    $this->assertNull($config->get('history_context_length'));

    $this->assertConfigSchemaByName('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
  }

  /**
   * Tests that history that was switched off stays switched off.
   */
  public function testLegacyDisabledHistoryStaysDisabled(): void {
    $this->writeLegacyAssistant('', 0);

    $assistant = $this->loadAssistant();

    $this->assertSame('', $assistant->get('allow_history'));
    $this->assertSame([], $assistant->get('chat_memory_settings'));
    $this->assertNull($assistant->getChatMemory());
  }

  /**
   * Tests that the legacy "none" setting is converted to no chat memory.
   *
   * 1.4.x offered "none" alongside the two session options, and
   * ai_assistant_api_post_update_convert_allow_history() mapped anything it
   * did not recognize to no plugin at all. An assistant that still carries it
   * must not keep a value that matches no chat memory plugin.
   */
  public function testLegacyNoneHistoryIsConverted(): void {
    $this->writeLegacyAssistant('none', 5);

    $assistant = $this->loadAssistant();

    $this->assertSame('', $assistant->get('allow_history'));
    $this->assertSame([], $assistant->get('chat_memory_settings'));
    $this->assertNull($assistant->getChatMemory());
  }

  /**
   * Tests that a saved "none" assistant is stored in the new shape.
   */
  public function testSavingLegacyNoneAssistantRewritesTheSettings(): void {
    $this->writeLegacyAssistant('none', 5);

    $this->loadAssistant()->save();

    $config = $this->config('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
    $this->assertSame('', $config->get('allow_history'));
    $this->assertNull($config->get('history_context_length'));
    $this->assertConfigSchemaByName('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID);
  }

  /**
   * Tests that a 1.4.x-era assistant config validates against the schema.
   *
   * Asserts the legacy payload itself, before the entity rewrites it into the
   * ChatMemory shape, so this fails if history_context_length loses its
   * type: ignore entry and an exported 1.4.x assistant stops importing.
   */
  public function testLegacyAssistantSettingsValidateAgainstTheSchema(): void {
    $this->assertConfigSchema(
      $this->container->get('config.typed'),
      'ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID,
      $this->buildLegacyAssistantData('session_one_thread', '5'),
    );
  }

  /**
   * Tests that saving a 1.4.x-era assistant payload passes validation.
   */
  public function testLegacyAssistantConfigImportsCleanly(): void {
    $name = 'ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID;
    $data = $this->buildLegacyAssistantData('session_one_thread', '5');

    // strictConfigSchema is on by default in kernel tests, so an undeclared
    // key in the payload makes this save throw.
    $this->config($name)->setData($data)->save();

    $this->assertSame('private_tempstore', $this->loadAssistant()->get('allow_history'));
  }

  /**
   * Tests that an assistant that is already migrated is left alone.
   */
  public function testMigratedAssistantIsLeftAlone(): void {
    $this->createAssistant();

    $assistant = $this->loadAssistant();

    $this->assertSame('private_tempstore', $assistant->get('allow_history'));
    $this->assertSame([
      'expiry' => 86400,
      'max_messages' => 0,
    ], $assistant->get('chat_memory_settings'));
  }

  /**
   * Loads a fresh copy of the test assistant.
   *
   * @return \Drupal\ai_assistant_api\Entity\AiAssistant
   *   The assistant.
   */
  protected function loadAssistant(): AiAssistant {
    $storage = $this->container->get('entity_type.manager')->getStorage('ai_assistant');
    $storage->resetCache([self::ASSISTANT_ID]);
    /** @var \Drupal\ai_assistant_api\Entity\AiAssistant $assistant */
    $assistant = $storage->load(self::ASSISTANT_ID);
    $this->assertInstanceOf(AiAssistant::class, $assistant);
    return $assistant;
  }

  /**
   * Creates a test assistant in the current, post-ChatMemory shape.
   */
  protected function createAssistant(): void {
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
  }

  /**
   * Writes an ai_assistant config in the legacy, pre chat-memory shape.
   *
   * A valid assistant is created through the entity API first, so that every
   * field except the two under test is in the current shape. The config is
   * then rewritten directly in storage to look like a pre-update site.
   *
   * @param string $allow_history
   *   The legacy allow_history value ('', 'session' or 'session_one_thread').
   * @param string|int $history_context_length
   *   The legacy history_context_length value.
   */
  protected function writeLegacyAssistant(string $allow_history, string|int $history_context_length): void {
    $data = $this->buildLegacyAssistantData($allow_history, $history_context_length);
    $this->container->get('config.storage')
      ->write('ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID, $data);

    $this->container->get('entity_type.manager')->getStorage('ai_assistant')->resetCache([self::ASSISTANT_ID]);
  }

  /**
   * Builds ai_assistant config data in the legacy, pre chat-memory shape.
   *
   * A valid assistant is created through the entity API first, so that every
   * field except the two under test is in the current shape.
   *
   * @param string $allow_history
   *   The legacy allow_history value ('', 'none', 'session' or
   *   'session_one_thread').
   * @param string|int $history_context_length
   *   The legacy history_context_length value.
   *
   * @return array
   *   The assistant config data, in the legacy shape.
   */
  protected function buildLegacyAssistantData(string $allow_history, string|int $history_context_length): array {
    $this->createAssistant();

    $name = 'ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID;
    $data = $this->container->get('config.storage')->read($name);
    unset($data['chat_memory_settings']);
    $data['allow_history'] = $allow_history;
    $data['history_context_length'] = $history_context_length;

    return $data;
  }

}
