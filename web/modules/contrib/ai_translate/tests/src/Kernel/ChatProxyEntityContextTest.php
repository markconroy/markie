<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai_translate\AiTranslateEntityContext;
use Drupal\ai_translate\AiTranslateRequestTags;
use Drupal\ai_translate\AiTranslateLanguageContext;
use Drupal\ai_translate\Controller\AiTranslateController;
use Drupal\ai_translate_test\Plugin\AiProvider\CountingChatProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity and language context on the chat-proxy ChatInput.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
final class ChatProxyEntityContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'language',
    'ai',
    'content_translation',
    'ai_translate',
    'ai_translate_test',
  ];

  /**
   * Target language used throughout.
   */
  protected LanguageInterface $german;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!method_exists(TranslateTextInput::class, 'setRequestMetadataValue')
      || !method_exists(ChatInput::class, 'getRequestMetadataValue')) {
      $this->markTestSkipped(
        'This AI version does not store request metadata on inputs.',
      );
    }
    $this->installConfig(['ai', 'ai_translate', 'language']);
    $this->installEntitySchema('configurable_language');
    $this->container->get('entity_type.manager')
      ->getStorage('configurable_language')
      ->create(['id' => 'de', 'label' => 'German'])
      ->save();

    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'chat_translation',
        'model_id' => 'chat-1',
      ])
      ->set('default_providers.chat', [
        'provider_id' => 'ai_translate_counting_chat',
        'model_id' => 'chat-1',
      ])
      ->save();

    CountingChatProvider::$calls = [];
    CountingChatProvider::$lastInput = NULL;
    CountingChatProvider::$lastTags = [];
    $this->german = \Drupal::languageManager()->getLanguage('de');
  }

  /**
   * Tests entity_context on translateContent reaches the inner chat call.
   */
  public function testEntityContextIsCopiedOntoChatInput(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german, NULL, [
      AiTranslateEntityContext::KEY => [
        'entity_type' => 'node',
        'bundle' => 'article',
        'id' => '8',
      ],
    ]);

    $this->assertSame(
      AiTranslateRequestTags::forChat(),
      CountingChatProvider::$lastTags,
    );
    $this->assertNotNull(CountingChatProvider::$lastInput);
    $this->assertSame([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => '8',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateEntityContext::KEY,
    ));
    $this->assertSame([
      'target' => 'de',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateLanguageContext::KEY,
    ));
  }

  /**
   * Tests string translation without an entity does not invent context.
   */
  public function testMissingEntityContextIsNotInvented(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);

    $this->assertSame(
      AiTranslateRequestTags::forChat(),
      CountingChatProvider::$lastTags,
    );
    $this->assertNotNull(CountingChatProvider::$lastInput);
    $this->assertNull(
      CountingChatProvider::$lastInput->getRequestMetadataValue(
        AiTranslateEntityContext::KEY,
      ),
    );
    $this->assertSame([
      'target' => 'de',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateLanguageContext::KEY,
    ));
  }

  /**
   * Tests source and target langcodes reach the inner chat call.
   */
  public function testLanguageContextIncludesSourceAndTarget(): void {
    $english = \Drupal::languageManager()->getLanguage('en');
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent(
      'Our services',
      $this->german,
      $english,
    );

    $this->assertNotNull(CountingChatProvider::$lastInput);
    $this->assertSame([
      'target' => 'de',
      'source' => 'en',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateLanguageContext::KEY,
    ));
  }

  /**
   * Tests the Translate-tab batch callback keeps entity_context.
   */
  public function testBatchCallbackKeepsEntityContextAfterSerialize(): void {
    $controller = AiTranslateController::create($this->container);
    $context_property = new \ReflectionProperty(
      $controller,
      'entityContext',
    );
    $context_property->setValue($controller, [
      AiTranslateEntityContext::KEY => [
        'entity_type' => 'node',
        'bundle' => 'article',
        'id' => '8',
      ],
    ]);

    $unserialized = unserialize(serialize($controller), [
      'allowed_classes' => [AiTranslateController::class],
    ]);
    $this->assertInstanceOf(AiTranslateController::class, $unserialized);

    $english = \Drupal::languageManager()->getLanguage('en');
    $batch = [];
    $unserialized->translateSingleField(
      ['_columns' => ['value'], 'value' => 'Our services'],
      $english,
      $this->german,
      $batch,
    );

    $this->assertNotNull(CountingChatProvider::$lastInput);
    $this->assertSame([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => '8',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateEntityContext::KEY,
    ));
    $this->assertSame([
      'target' => 'de',
      'source' => 'en',
    ], CountingChatProvider::$lastInput->getRequestMetadataValue(
      AiTranslateLanguageContext::KEY,
    ));
  }

}
