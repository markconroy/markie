<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\block\Entity\Block;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_chatbot_post_update_chat_processor() post update hook.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
class ChangeToChatProcessorTest extends KernelTestBase {

  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'key',
    'ai',
    'ai_assistant_api',
    'ai_chatbot',
    'block',
  ];

  /**
   * The ID of the AI Assistant used across the tests.
   */
  protected const ASSISTANT_ID = 'test_assistant';

  /**
   * The ID of the block placed for the tests.
   */
  protected const BLOCK_ID = 'test_deepchat_block';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    AiAssistant::create([
      'id' => self::ASSISTANT_ID,
      'label' => 'Test Assistant',
      'description' => '',
      'instructions' => '',
      'allow_history' => '',
      'error_message' => 'An error occurred.',
      'llm_provider' => 'echoai',
      'llm_model' => 'gpt-test',
      'llm_configuration' => [],
      'specific_error_messages' => [],
    ])->save();
  }

  /**
   * Tests migration of legacy assistant/stream/verbose block settings.
   *
   * Old format: the block stores ai_assistant, stream, verbose_mode and
   * show_structured_results as top level settings.
   *
   * New format: those are consolidated under chat_processor_plugin (set to
   * ai_assistant_api_processor) and plugin_configuration.
   */
  public function testPostUpdateHookMigratesPluginConfig(): void {
    $this->writeLegacyDeepChatBlock([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 1,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ]);

    $this->runChatProcessorPostUpdate();

    $block = Block::load(self::BLOCK_ID);
    $this->assertInstanceOf(Block::class, $block);
    $settings = $block->get('settings');

    $this->assertSame('ai_assistant_api_processor', $settings['chat_processor_plugin']);
    $this->assertSame([
      'assistant_id' => self::ASSISTANT_ID,
      'stream_output' => TRUE,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ], $settings['plugin_configuration']);

    // The legacy top-level keys must have been removed from settings.
    $this->assertArrayNotHasKey('verbose_mode', $settings);
    $this->assertArrayNotHasKey('show_structured_results', $settings);
    $this->assertArrayNotHasKey('ai_assistant', $settings);

    // The remaining, unrelated settings must be preserved untouched.
    $this->assertSame('Assistant', $settings['bot_name']);
    $this->assertSame(1, $settings['stream']);

    $this->assertConfigSchemaByName('block.block.' . self::BLOCK_ID);
  }

  /**
   * Tests migration when legacy boolean flags are unset/falsy.
   */
  public function testPostUpdateHookMigratesPluginConfigWithDefaults(): void {
    $this->writeLegacyDeepChatBlock([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 0,
    ]);

    $this->runChatProcessorPostUpdate();

    $block = Block::load(self::BLOCK_ID);
    $settings = $block->get('settings');

    $this->assertSame('ai_assistant_api_processor', $settings['chat_processor_plugin']);
    $this->assertSame([
      'assistant_id' => self::ASSISTANT_ID,
      'stream_output' => FALSE,
      // 1.4.x defaulted verbose_mode to TRUE, so a block that never saved the
      // setting - including the one Drupal CMS installs - stays verbose.
      'verbose_mode' => TRUE,
      'show_structured_results' => FALSE,
    ], $settings['plugin_configuration']);

    $this->assertConfigSchemaByName('block.block.' . self::BLOCK_ID);
  }

  /**
   * Tests that blocks already migrated to the chat processor are untouched.
   */
  public function testPostUpdateHookSkipsAlreadyMigratedBlocks(): void {
    $this->writeDeepChatBlockSettings([
      'id' => 'ai_deepchat_block',
      'label' => 'Test DeepChat Block',
      'label_display' => '0',
      'provider' => 'ai_chatbot',
      'bot_name' => 'Assistant',
      'stream' => 1,
      'chat_processor_plugin' => 'ai_assistant_api_processor',
      'plugin_configuration' => [
        'assistant_id' => self::ASSISTANT_ID,
        'stream_output' => TRUE,
        'verbose_mode' => TRUE,
        'show_structured_results' => TRUE,
      ],
    ]);

    $this->runChatProcessorPostUpdate();

    $block = Block::load(self::BLOCK_ID);
    $settings = $block->get('settings');

    $this->assertSame([
      'assistant_id' => self::ASSISTANT_ID,
      'stream_output' => TRUE,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ], $settings['plugin_configuration']);

    $this->assertConfigSchemaByName('block.block.' . self::BLOCK_ID);
  }

  /**
   * Writes a legacy-format (pre chat-processor) ai_deepchat_block block.
   *
   * @param array $settings
   *   Legacy settings to merge on top of the defaults.
   */
  protected function writeLegacyDeepChatBlock(array $settings = []): void {
    $settings += [
      'id' => 'ai_deepchat_block',
      'label' => 'Test DeepChat Block',
      'label_display' => '0',
      'provider' => 'ai_chatbot',
      'bot_name' => 'Assistant',
    ];
    $this->writeDeepChatBlockSettings($settings);
  }

  /**
   * Writes an ai_deepchat_block block directly to config storage.
   *
   * Bypasses the entity API/config schema checker so settings that predate
   * the current block.settings.ai_deepchat_block schema can be written.
   *
   * @param array $settings
   *   The full block plugin settings array.
   */
  protected function writeDeepChatBlockSettings(array $settings): void {
    $storage = $this->container->get('config.storage');
    $storage->write('block.block.' . self::BLOCK_ID, [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
        'config' => ['ai_assistant_api.ai_assistant.' . self::ASSISTANT_ID],
        'module' => ['ai_chatbot'],
        'theme' => ['stark'],
      ],
      'id' => self::BLOCK_ID,
      'theme' => 'stark',
      'region' => 'content',
      'weight' => 0,
      'provider' => 'block',
      'plugin' => 'ai_deepchat_block',
      'settings' => $settings,
      'visibility' => [],
    ]);
    $this->container->get('entity_type.manager')->getStorage('block')->resetCache([self::BLOCK_ID]);
  }

  /**
   * Runs the ai_chatbot_post_update_chat_processor() post update hook.
   */
  protected function runChatProcessorPostUpdate(): void {
    $this->container->get('module_handler')->loadInclude('ai_chatbot', 'php', 'ai_chatbot.post_update');
    $sandbox = [];
    ai_chatbot_post_update_chat_processor($sandbox);
  }

}
