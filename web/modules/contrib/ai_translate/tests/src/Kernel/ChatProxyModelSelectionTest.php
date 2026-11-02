<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_translate\ChatModelResolver;
use Drupal\ai_translate\ChatModelSelection;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate_test\Plugin\AiProvider\CountingChatProvider;
use Drupal\ai_translate_test\Plugin\AiProvider\SecondChatProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which provider and model the chat proxy delegates to.
 *
 * The proxy translates by running the chat operation, choosing the provider
 * and model per target language: the language-specific setting first, then
 * the model selected for the translate_text operation, then the chat default.
 * A candidate its provider no longer offers is passed over.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
class ChatProxyModelSelectionTest extends KernelTestBase {

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
   * The translator under test.
   */
  protected TextTranslatorInterface $textTranslator;

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The state store the test hook records into.
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai', 'ai_translate', 'language']);
    $this->installEntitySchema('configurable_language');
    foreach (['de' => 'German', 'fr' => 'French'] as $id => $label) {
      $this->container->get('entity_type.manager')
        ->getStorage('configurable_language')
        ->create(['id' => $id, 'label' => $label])
        ->save();
    }

    // The cache is exercised separately, in ChatProxyTranslationCacheTest.
    $this->config('ai_translate.settings')
      ->set('cache_translations', FALSE)
      ->save();

    $this->config('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'ai_translate_counting_chat',
        'model_id' => 'chat-1',
      ])
      ->save();

    $this->textTranslator = $this->container->get('ai_translate.text_translator');
    $this->languageManager = $this->container->get('language_manager');
    $this->state = $this->container->get('state');

    CountingChatProvider::$calls = [];
    SecondChatProvider::$calls = [];
    SecondChatProvider::$modelListFails = FALSE;
    SecondChatProvider::$modelListCalls = 0;
  }

  /**
   * With nothing more specific set, the default chat model translates.
   */
  public function testFallsBackToTheDefaultChatModel(): void {
    $this->setTranslateTextModel('');

    $this->translate('de');

    $this->assertSame(['chat-1'], CountingChatProvider::$calls);
  }

  /**
   * The model selected for the translate_text operation is honored.
   */
  public function testTranslateTextModelIsUsed(): void {
    $this->setTranslateTextModel('chat-2');

    $result = $this->translate('de');

    $this->assertSame(['chat-2'], CountingChatProvider::$calls,
      'The model chosen for the translation operation ran, not the chat default.');
    $this->assertSame('translated by chat-2', $result);
  }

  /**
   * A per-language model beats the translate_text model.
   */
  public function testPerLanguageModelWins(): void {
    $this->setTranslateTextModel('chat-2');
    $this->setLanguageModel('de', 'ai_translate_counting_chat__chat-1');

    $this->translate('de');

    $this->assertSame(['chat-1'], CountingChatProvider::$calls,
      'The more specific per-language setting took precedence.');
  }

  /**
   * A per-language setting can select a different provider, not just a model.
   */
  public function testPerLanguageSettingCanSwitchProvider(): void {
    $this->setTranslateTextModel('chat-2');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');

    $result = $this->translate('de');

    $this->assertSame(['second-1'], SecondChatProvider::$calls,
      'The language-specific provider did the work.');
    $this->assertSame([], CountingChatProvider::$calls,
      'The default chat provider was not called at all.');
    $this->assertSame('translated by second-1', $result);
  }

  /**
   * Each language follows its own setting within a single request.
   */
  public function testEachLanguageResolvesSeparately(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');
    $this->setLanguageModel('fr', 'ai_translate_counting_chat__chat-2');

    $this->translate('de');
    $this->translate('fr');

    $this->assertSame(['second-1'], SecondChatProvider::$calls,
      'German went to the second provider.');
    $this->assertSame(['chat-2'], CountingChatProvider::$calls,
      'French went to the counting provider, rather than reusing German\'s choice.');
  }

  /**
   * A model the provider no longer offers is discarded, not passed on.
   */
  public function testStaleTranslateTextModelFallsBack(): void {
    $this->setTranslateTextModel('model-from-a-provider-since-removed');

    $this->translate('de');

    $this->assertSame(['chat-1'], CountingChatProvider::$calls,
      'The unknown model was dropped in favor of the chat default.');
  }

  /**
   * A stale per-language setting falls back rather than failing.
   */
  public function testStalePerLanguageModelFallsBack(): void {
    $this->setTranslateTextModel('chat-2');
    $this->setLanguageModel('de', 'ai_translate_second_chat__model-that-went-away');

    $this->translate('de');

    $this->assertSame([], SecondChatProvider::$calls,
      'The second provider was never handed a model it does not offer.');
    $this->assertSame(['chat-2'], CountingChatProvider::$calls,
      'Resolution fell through to the translate_text model.');
  }

  /**
   * A per-language setting naming a provider that is gone falls back.
   */
  public function testPerLanguageUnknownProviderFallsBack(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_provider_that_never_existed__some-model');

    $this->translate('de');

    $this->assertSame(['chat-1'], CountingChatProvider::$calls);
  }

  /**
   * A per-language setting that is not a provider/model pair falls back.
   *
   * The settings form cannot produce such a value, but a config import or a
   * settings.php override can, and the form still renders it as a selection.
   */
  public function testMalformedPerLanguageModelFallsBack(): void {
    $this->setTranslateTextModel('chat-2');
    $this->setLanguageModel('de', 'not-a-provider-model-pair');

    $this->translate('de');

    $this->assertSame([], SecondChatProvider::$calls,
      'No provider was picked from an unparseable setting.');
    $this->assertSame(['chat-2'], CountingChatProvider::$calls,
      'Resolution fell through to the translate_text model.');
  }

  /**
   * A provider that cannot list its models is passed over, not fatal.
   *
   * Asking costs a call into the provider, and that call is the one that fails
   * on a site whose key has lapsed. Falling through is the whole point of
   * asking, so the failure must not escape as a TranslationException.
   */
  public function testUnreachableModelListFallsBack(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');
    SecondChatProvider::$modelListFails = TRUE;

    $this->translate('de');

    $this->assertSame([], SecondChatProvider::$calls,
      'The provider that could not answer was never handed the translation.');
    $this->assertSame(['chat-1'], CountingChatProvider::$calls,
      'Resolution fell through to the chat default.');
  }

  /**
   * The resolver names the provider and model that will run.
   */
  public function testResolverReturnsTheSelection(): void {
    $resolver = $this->container->get('ai_translate.chat_model_resolver');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');

    $selection = $resolver->resolve('chat-2', $this->languageManager->getLanguage('de'));
    $this->assertInstanceOf(ChatModelSelection::class, $selection);
    $this->assertSame('ai_translate_second_chat', $selection->providerId);
    $this->assertSame('second-1', $selection->modelId);

    $selection = $resolver->resolve('chat-2', $this->languageManager->getLanguage('fr'));
    $this->assertSame([
      'provider_id' => 'ai_translate_counting_chat',
      'model_id' => 'chat-2',
    ], $selection?->toArray());
  }

  /**
   * Without any chat provider to run on, the resolver says so.
   */
  public function testResolverReturnsNullWithoutChatProvider(): void {
    $this->config('ai.settings')
      ->clear('default_providers.chat')
      ->save();
    $resolver = $this->container->get('ai_translate.chat_model_resolver');

    $this->assertNull($resolver->resolve('chat-2', $this->languageManager->getLanguage('de')));

    // A provider set for the target language still applies.
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');
    $this->assertSame('ai_translate_second_chat',
      $resolver->resolve('', $this->languageManager->getLanguage('de'))?->providerId);
  }

  /**
   * The alter hook is told the provider and model that will run.
   *
   * @see ai_translate_test_ai_translate_translation_alter()
   */
  public function testAlterHookReceivesTheResolvedModel(): void {
    $this->setTranslateTextModel('chat-2');

    $this->translate('de');

    $this->assertSame(['ai_translate_counting_chat', 'chat-2'],
      $this->state->get('ai_translate_test.alter_args'),
      'The hook was told the resolved provider and model.');
  }

  /**
   * A model check is reused by later requests, not asked again.
   */
  public function testModelCheckIsReusedAcrossRequests(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');

    $this->translate('de');
    $this->startNewRequest();
    $this->translate('de');

    $this->assertSame(['second-1', 'second-1'], SecondChatProvider::$calls);
    $this->assertSame(1, SecondChatProvider::$modelListCalls,
      'The second request used the stored answer instead of asking the provider.');
  }

  /**
   * A model check that failed is asked again by the next request.
   */
  public function testFailedModelCheckIsNotStored(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');
    SecondChatProvider::$modelListFails = TRUE;

    $this->translate('de');

    SecondChatProvider::$modelListFails = FALSE;
    $this->startNewRequest();
    $this->translate('de');

    $this->assertSame(['chat-1'], CountingChatProvider::$calls,
      'While the provider could not answer, the chat default translated.');
    $this->assertSame(['second-1'], SecondChatProvider::$calls,
      'Once it could, the per-language provider was used again.');
  }

  /**
   * Saving the AI settings drops stored model checks.
   *
   * Models added or relabelled on the AI settings form live in ai.settings,
   * so a stored answer may no longer hold once it is saved.
   */
  public function testSavingAiSettingsDropsStoredModelChecks(): void {
    $this->setTranslateTextModel('');
    $this->setLanguageModel('de', 'ai_translate_second_chat__second-1');

    $this->translate('de');
    $this->setTranslateTextModel('');
    $this->startNewRequest();
    $this->translate('de');

    $this->assertSame(2, SecondChatProvider::$modelListCalls);
  }

  /**
   * Translates one string, going through the whole TextTranslator path.
   *
   * @param string $langcode
   *   Target language code.
   *
   * @return string
   *   The translation.
   */
  protected function translate(string $langcode): string {
    return $this->textTranslator->translateContent(
      'Our services',
      $this->languageManager->getLanguage($langcode)
    );
  }

  /**
   * Replaces the resolver, as a new request would start with a fresh one.
   *
   * The resolver also remembers its answers for the rest of the request, which
   * would hide whether the cache bin is consulted at all.
   */
  protected function startNewRequest(): void {
    $this->container->set('ai_translate.chat_model_resolver', new ChatModelResolver(
      $this->container->get('ai.provider'),
      $this->container->get('config.factory'),
      $this->container->get('logger.factory'),
      $this->container->get('cache.ai_translate'),
      $this->container->get('datetime.time'),
    ));
  }

  /**
   * Points translate_text at the proxy with the given model.
   *
   * @param string $model
   *   Model ID, or an empty string for none.
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
   * Sets the per-language provider and model for one language.
   *
   * @param string $langcode
   *   Language code.
   * @param string $value
   *   Value as the settings form stores it, "provider_id__model_id".
   */
  protected function setLanguageModel(string $langcode, string $value): void {
    $this->config('ai_translate.settings')
      ->set('language_settings.' . $langcode . '.model', $value)
      ->save();
  }

}
