<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\block\Entity\Block;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests DeepChat blocks that still carry pre-ChatProcessor settings.
 *
 * Such blocks are normally migrated by
 * ai_chatbot_post_update_chat_processor(), but config exported before that
 * update ran - or imported, or installed by a recipe, afterwards - arrives in
 * the old shape, so the block plugin has to cope with it at runtime.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
class LegacyDeepChatBlockSettingsTest extends KernelTestBase {

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
   * Tests that an unmigrated block runs on the AI Assistant API processor.
   */
  public function testLegacySettingsConfigureTheAssistantProcessor(): void {
    $this->writeDeepChatBlock([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 1,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ]);

    $configuration = $this->loadBlockPluginConfiguration();

    $this->assertSame('ai_assistant_api_processor', $configuration['chat_processor_plugin']);
    $this->assertSame([
      'assistant_id' => self::ASSISTANT_ID,
      'stream_output' => TRUE,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ], $configuration['plugin_configuration']);

    // The legacy settings are not part of the block configuration any more.
    $this->assertArrayNotHasKey('ai_assistant', $configuration);
    $this->assertArrayNotHasKey('verbose_mode', $configuration);
    $this->assertArrayNotHasKey('show_structured_results', $configuration);

    // Unrelated settings are untouched.
    $this->assertSame(1, $configuration['stream']);
  }

  /**
   * Tests that saving an unmigrated block stores it in the new shape.
   */
  public function testSavingAnUnmigratedBlockRewritesTheSettings(): void {
    $this->writeDeepChatBlock([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 0,
    ]);

    $block = Block::load(self::BLOCK_ID);
    $this->assertInstanceOf(Block::class, $block);
    $block->save();

    $settings = Block::load(self::BLOCK_ID)->get('settings');
    $this->assertSame('ai_assistant_api_processor', $settings['chat_processor_plugin']);
    $this->assertSame([
      'assistant_id' => self::ASSISTANT_ID,
      'stream_output' => FALSE,
      // 1.4.x defaulted verbose_mode to TRUE, so a legacy block that never
      // saved the setting stays verbose.
      'verbose_mode' => TRUE,
      'show_structured_results' => FALSE,
    ], $settings['plugin_configuration']);
    $this->assertArrayNotHasKey('ai_assistant', $settings);

    $this->assertConfigSchemaByName('block.block.' . self::BLOCK_ID);
  }

  /**
   * Tests that a 1.4.x-era block config validates against the schema.
   *
   * Asserts the legacy payload itself, before anything rewrites it into the
   * ChatProcessor shape, so this fails if the deprecated keys lose their
   * type: ignore entries and an exported 1.4.x block stops importing.
   */
  public function testLegacyBlockSettingsValidateAgainstTheSchema(): void {
    $data = $this->buildDeepChatBlockConfig([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 1,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ]);

    $this->assertConfigSchema(
      $this->container->get('config.typed'),
      'block.block.' . self::BLOCK_ID,
      $data,
    );
  }

  /**
   * Tests that importing a 1.4.x-era block config passes validation.
   *
   * Goes through the config importer rather than config.storage, so the
   * legacy payload is validated the way a real deployment validates it.
   */
  public function testLegacyBlockConfigImportsCleanly(): void {
    $data = $this->buildDeepChatBlockConfig([
      'ai_assistant' => self::ASSISTANT_ID,
      'stream' => 1,
      'verbose_mode' => TRUE,
      'show_structured_results' => TRUE,
    ]);

    // strictConfigSchema is on by default in kernel tests, so an undeclared
    // key in the payload makes this save throw.
    $this->config('block.block.' . self::BLOCK_ID)->setData($data)->save();

    $block = Block::load(self::BLOCK_ID);
    $this->assertInstanceOf(Block::class, $block);
    $this->assertSame(
      'ai_assistant_api_processor',
      $block->getPlugin()->getConfiguration()['chat_processor_plugin'],
    );
  }

  /**
   * Tests that a configured chat processor wins over the legacy settings.
   */
  public function testConfiguredChatProcessorIsKept(): void {
    $this->writeDeepChatBlock([
      'ai_assistant' => 'stale_assistant',
      'chat_processor_plugin' => 'ai_assistant_api_processor',
      'plugin_configuration' => [
        'assistant_id' => self::ASSISTANT_ID,
        'stream_output' => TRUE,
        'verbose_mode' => TRUE,
        'show_structured_results' => FALSE,
      ],
    ]);

    $configuration = $this->loadBlockPluginConfiguration();

    $this->assertSame('ai_assistant_api_processor', $configuration['chat_processor_plugin']);
    $this->assertSame(self::ASSISTANT_ID, $configuration['plugin_configuration']['assistant_id']);
    $this->assertArrayNotHasKey('ai_assistant', $configuration);
  }

  /**
   * Tests that a block without legacy settings keeps no chat processor.
   */
  public function testBlockWithoutLegacySettingsIsLeftAlone(): void {
    $this->writeDeepChatBlock([
      'chat_processor_plugin' => '',
      'plugin_configuration' => [],
    ]);

    $configuration = $this->loadBlockPluginConfiguration();

    $this->assertSame('', $configuration['chat_processor_plugin']);
    $this->assertSame([], $configuration['plugin_configuration']);
  }

  /**
   * Gets the configuration of the placed block's plugin.
   *
   * @return array
   *   The block plugin configuration.
   */
  protected function loadBlockPluginConfiguration(): array {
    $block = Block::load(self::BLOCK_ID);
    $this->assertInstanceOf(Block::class, $block);
    return $block->getPlugin()->getConfiguration();
  }

  /**
   * Writes an ai_deepchat_block block directly to config storage.
   *
   * Bypasses the entity API so that settings which predate the current
   * block.settings.ai_deepchat_block schema can be written.
   *
   * @param array $settings
   *   Block plugin settings to merge on top of the required ones.
   */
  protected function writeDeepChatBlock(array $settings): void {
    $this->container->get('config.storage')->write(
      'block.block.' . self::BLOCK_ID,
      $this->buildDeepChatBlockConfig($settings),
    );
    $this->container->get('entity_type.manager')->getStorage('block')->resetCache([self::BLOCK_ID]);
  }

  /**
   * Builds the config object for an ai_deepchat_block block.
   *
   * @param array $settings
   *   Block plugin settings to merge on top of the required ones.
   *
   * @return array
   *   The block config data.
   */
  protected function buildDeepChatBlockConfig(array $settings): array {
    $settings += [
      'id' => 'ai_deepchat_block',
      'label' => 'Test DeepChat Block',
      'label_display' => '0',
      'provider' => 'ai_chatbot',
      'bot_name' => 'Assistant',
    ];
    return [
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [
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
    ];
  }

}
