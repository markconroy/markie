<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_translate_test\Plugin\AiProvider\CountingChatProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests caching when translate_text is handled by the chat proxy.
 *
 * This is the configuration docs/installation.md points anyone without a
 * dedicated translation provider at, and the one the cache ID has to get right.
 * The proxy picks its chat provider and model per target language, so every
 * input to that choice has to reach the cache key.
 *
 * @see \Drupal\ai_translate\ChatModelResolver::resolve()
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
class ChatProxyTranslationCacheTest extends KernelTestBase {

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
    'language',
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
    $this->installConfig(['ai', 'ai_translate', 'language']);
    $this->installEntitySchema('configurable_language');
    $this->container->get('entity_type.manager')
      ->getStorage('configurable_language')
      ->create(['id' => 'de', 'label' => 'German'])
      ->save();

    $this->setProviders('chat-1');
    CountingChatProvider::$calls = [];
    $this->german = \Drupal::languageManager()->getLanguage('de');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unset($GLOBALS['config']['ai_translate.settings']);
    parent::tearDown();
  }

  /**
   * Switching the default chat model produces a fresh translation.
   */
  public function testChangingTheChatModelForcesRetranslation(): void {
    $translator = \Drupal::service('ai_translate.text_translator');

    $first = $translator->translateContent('Our services', $this->german);
    $this->assertSame('translated by chat-1', $first,
      'The chat default decides the model, not the translate_text default.');

    $this->setProviders('chat-2');
    $second = $translator->translateContent('Our services', $this->german);

    $this->assertSame('translated by chat-2', $second,
      'The new model translated, rather than the old entry being served.');
    $this->assertSame(['chat-1', 'chat-2'], CountingChatProvider::$calls,
      'Both models were reached exactly once.');
  }

  /**
   * The chat model is in the ID, so switching back reuses the old entry.
   */
  public function testSwitchingBackReusesTheEarlierEntry(): void {
    $translator = \Drupal::service('ai_translate.text_translator');

    $translator->translateContent('Our services', $this->german);
    $this->setProviders('chat-2');
    $translator->translateContent('Our services', $this->german);
    $this->setProviders('chat-1');
    $back = $translator->translateContent('Our services', $this->german);

    $this->assertSame('translated by chat-1', $back);
    $this->assertSame(['chat-1', 'chat-2'], CountingChatProvider::$calls,
      'Going back to the first model cost no provider call.');
  }

  /**
   * An unchanged configuration still serves the stored translation.
   */
  public function testAnUnchangedConfigurationStillHitsTheCache(): void {
    $translator = \Drupal::service('ai_translate.text_translator');

    $translator->translateContent('Our services', $this->german);
    $translator->translateContent('Our services', $this->german);

    $this->assertSame(['chat-1'], CountingChatProvider::$calls,
      'The proxy path still caches; the fix did not disable it.');
  }

  /**
   * Changing the translate_text model produces a fresh translation.
   */
  public function testChangingTheTranslateTextModelForcesRetranslation(): void {
    $translator = $this->container->get('ai_translate.text_translator');

    $this->setTranslateTextModel('chat-1');
    $this->assertSame('translated by chat-1',
      $translator->translateContent('Our services', $this->german));

    $this->setTranslateTextModel('chat-2');
    $this->assertSame('translated by chat-2',
      $translator->translateContent('Our services', $this->german),
      'The newly selected model translated, rather than the stored entry being served.');

    $this->assertSame(['chat-1', 'chat-2'], CountingChatProvider::$calls);
  }

  /**
   * Changing the per-language model produces a fresh translation.
   */
  public function testChangingThePerLanguageModelForcesRetranslation(): void {
    $translator = $this->container->get('ai_translate.text_translator');

    $this->setLanguageModel('ai_translate_counting_chat__chat-1');
    $this->assertSame('translated by chat-1',
      $translator->translateContent('Our services', $this->german));

    $this->setLanguageModel('ai_translate_counting_chat__chat-2');
    $this->assertSame('translated by chat-2',
      $translator->translateContent('Our services', $this->german),
      'The newly selected model translated, rather than the stored entry being served.');

    $this->assertSame(['chat-1', 'chat-2'], CountingChatProvider::$calls);
  }

  /**
   * A per-language model set in settings.php produces a fresh translation.
   *
   * Saving the setting, as the test above does, invalidates the
   * ai_translate.settings cache tag, which forces retranslation whatever the
   * cache ID holds. An override in settings.php is never saved, so nothing is
   * invalidated and only the per-language model in the cache ID tells the two
   * apart.
   *
   * @see \Drupal\ai_translate\TextTranslator::providerSignature()
   */
  public function testOverridingThePerLanguageModelForcesRetranslation(): void {
    $translator = $this->container->get('ai_translate.text_translator');

    $this->setLanguageModel('ai_translate_counting_chat__chat-1');
    $this->assertSame('translated by chat-1',
      $translator->translateContent('Our services', $this->german));

    $GLOBALS['config']['ai_translate.settings']['language_settings']['de']['model'] = 'ai_translate_counting_chat__chat-2';
    $this->container->get('config.factory')->reset('ai_translate.settings');

    $this->assertSame('translated by chat-2',
      $translator->translateContent('Our services', $this->german),
      'The per-language model is part of the cache ID.');

    $this->assertSame(['chat-1', 'chat-2'], CountingChatProvider::$calls);
  }

  /**
   * Sets the model for the translate_text operation.
   *
   * @param string $model
   *   Model ID.
   */
  protected function setTranslateTextModel(string $model): void {
    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'chat_translation',
        'model_id' => $model,
      ])
      ->save();
  }

  /**
   * Sets the per-language provider and model for German.
   *
   * @param string $value
   *   Value as the settings form stores it, "provider_id__model_id".
   */
  protected function setLanguageModel(string $value): void {
    $this->config('ai_translate.settings')
      ->set('language_settings.de.model', $value)
      ->save();
  }

  /**
   * Points translate_text at the proxy and the proxy at a chat model.
   *
   * @param string $chatModel
   *   The default chat model, which is what actually runs.
   */
  protected function setProviders(string $chatModel): void {
    $this->config('ai.settings')
      // No model for the translation operation, so the chat default is what
      // decides. Setting one here would take precedence and leave these tests
      // varying nothing.
      ->set('default_providers.translate_text', [
        'provider_id' => 'chat_translation',
        'model_id' => '',
      ])
      ->set('default_providers.chat', [
        'provider_id' => 'ai_translate_counting_chat',
        'model_id' => $chatModel,
      ])
      ->save();
  }

}
