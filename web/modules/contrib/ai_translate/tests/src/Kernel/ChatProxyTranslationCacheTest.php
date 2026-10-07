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
 * dedicated translation provider at, and the one the cache ID has to get right:
 * the proxy discards the model it is handed and uses the default chat model
 * instead, so the chat default is what decides the output.
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
   * Points translate_text at the proxy and the proxy at a chat model.
   *
   * @param string $chatModel
   *   The default chat model, which is what actually runs.
   */
  protected function setProviders(string $chatModel): void {
    $this->config('ai.settings')
      // The model_id here is deliberately left on chat-1 throughout. The proxy
      // ignores it, so a test that changed it too could not tell whether the
      // fix reads the chat default or merely the translate_text default.
      ->set('default_providers.translate_text', [
        'provider_id' => 'chat_translation',
        'model_id' => 'chat-1',
      ])
      ->set('default_providers.chat', [
        'provider_id' => 'ai_translate_counting_chat',
        'model_id' => $chatModel,
      ])
      ->save();
  }

}
