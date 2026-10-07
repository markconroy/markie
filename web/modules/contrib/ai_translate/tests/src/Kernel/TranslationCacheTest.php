<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_translate\Controller\AiTranslateController;
use Drupal\ai_translate\TextTranslator;
use Drupal\ai_translate_test\Plugin\AiProvider\CountingTranslateProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests translation caching against a real provider plugin.
 *
 * Covers what the unit test cannot: storing a result, the cache tags, and the
 * container wiring. The counting provider plugin is reached the way production
 * reaches a provider, through AiProviderPluginManager::createInstance().
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
class TranslationCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'ai',
    'ai_translate',
    'ai_translate_test',
  ];

  /**
   * The dedicated translation cache bin.
   */
  protected CacheBackendInterface $bin;

  /**
   * Target language used throughout.
   */
  protected Language $german;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai', 'ai_translate']);

    // Point the translate_text operation at the counting stub, so nothing ever
    // leaves the test and calls can be counted.
    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'ai_translate_counting',
        'model_id' => 'counter-1',
      ])
      ->save();

    CountingTranslateProvider::$calls = [];
    $this->bin = \Drupal::service('cache.ai_translate');
    $this->german = new Language(['id' => 'de', 'name' => 'German']);
  }

  /**
   * The translator is the plain class on its own cache bin.
   */
  public function testTheServicesAreWiredUp(): void {
    $this->assertInstanceOf(TextTranslator::class,
      \Drupal::service('ai_translate.text_translator'),
      'The translator is the class itself, with no decorator in front of it.');

    $this->assertInstanceOf(CacheBackendInterface::class,
      \Drupal::service('cache.ai_translate'),
      'A dedicated cache bin exists rather than reusing cache.default.');
  }

  /**
   * Caching is on for a new install and left off by the update path.
   */
  public function testDefaultIsOnForNewInstallsAndOffAfterUpdate(): void {
    $this->assertTrue(
      $this->config('ai_translate.settings')->get('cache_translations'),
      'A new install has translation caching enabled.');

    $file = \Drupal::service('extension.list.module')->getPath('ai_translate')
      . '/ai_translate.post_update.php';
    require_once $file;
    $message = ai_translate_post_update_disable_translation_cache();

    $this->assertFalse(
      $this->config('ai_translate.settings')->get('cache_translations'),
      'The update path leaves existing sites with caching disabled.');
    $this->assertStringContainsString('Cache translation results', (string) $message,
      'The update tells the site builder which setting to switch on.');
  }

  /**
   * A translation is stored on the first call and reused on the second.
   */
  public function testTranslationIsStoredAndThenReused(): void {
    $translator = \Drupal::service('ai_translate.text_translator');

    $first = $translator->translateContent('Our services', $this->german);
    $second = $translator->translateContent('Our services', $this->german);

    $this->assertSame('DE: Our services', $first);
    $this->assertSame($first, $second, 'The second call returned the stored result.');
    $this->assertSame(['Our services'], CountingTranslateProvider::$calls,
      'The provider was called once, not twice.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entry was written to the dedicated bin under the expected ID.');
  }

  /**
   * Two fields holding the same string cost one provider call in a single run.
   *
   * Drives the real batch callback rather than the translator directly, so it
   * covers the scenario the issue describes.
   */
  public function testRepeatedFieldsInOneRunCostOneProviderCall(): void {
    $controller = AiTranslateController::create($this->container);
    $english = \Drupal::languageManager()->getLanguage('en');
    $context = [];

    $repeated = 'Read more about our services';
    foreach ([$repeated, 'We build digital platforms', $repeated] as $value) {
      $controller->translateSingleField(
        ['_columns' => ['value'], 'value' => $value],
        $english,
        $this->german,
        $context
      );
    }

    $this->assertCount(3, $context['results']['processedTranslations'],
      'All three fields were processed.');
    $this->assertSame([$repeated, 'We build digital platforms'],
      CountingTranslateProvider::$calls,
      'The repeated string reached the provider once, not twice.');
  }

  /**
   * The same holds through the orchestrator the batch callback delegates to.
   *
   * Deduplication is a property of the shared service rather than of the batch,
   * so the paths that do not run a batch - Drush, the Tool plugin and the
   * function call - get it as well.
   */
  public function testRepeatedFieldsCostOneProviderCallThroughTheOrchestrator(): void {
    /** @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface $orchestrator */
    $orchestrator = \Drupal::service('ai_translate.translation_orchestrator');
    $english = \Drupal::languageManager()->getLanguage('en');

    $repeated = 'Read more about our services';
    $translated = [];
    foreach ([$repeated, 'We build digital platforms', $repeated] as $value) {
      $translated[] = $orchestrator->translateTextMetadataItem(
        ['_columns' => ['value'], 'value' => $value],
        $english,
        $this->german
      );
    }

    $this->assertCount(3, array_filter($translated),
      'All three fields were translated.');
    $this->assertSame([$repeated, 'We build digital platforms'],
      CountingTranslateProvider::$calls,
      'The repeated string reached the provider once, not twice.');
    $this->assertSame('DE: ' . $repeated, $translated[2]['translated']['value'],
      'The repeated field was filled from the stored translation.');
  }

  /**
   * Changing the translation model does not serve the older translation.
   */
  public function testChangingTheModelDoesNotServeTheOldTranslation(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);

    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'ai_translate_counting',
        'model_id' => 'counter-2',
      ])
      ->save();

    $translator->translateContent('Our services', $this->german);

    $this->assertCount(2, CountingTranslateProvider::$calls,
      'The model is part of the cache ID, so the second call re-translated.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entry made by the first model is still there, just unused.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services', 'de', 'auto', 'counter-2')),
      'The second model produced an entry of its own.');
  }

  /**
   * An unrelated AI default change leaves stored translations alone.
   *
   * This is the behavior the tag on ai.settings used to get wrong: that file
   * holds the defaults for every operation type, so a tag on it dropped every
   * translation whenever any AI default was saved.
   */
  public function testAnUnrelatedAiSettingChangeKeepsStoredTranslations(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);

    $this->config('ai.settings')
      ->set('default_providers.text_to_speech', [
        'provider_id' => 'ai_translate_counting',
        'model_id' => 'counter-2',
      ])
      ->save();

    $translator->translateContent('Our services', $this->german);

    $this->assertCount(1, CountingTranslateProvider::$calls,
      'Saving an unrelated AI default did not drop the stored translation.');
  }

  /**
   * Saving the translation settings drops stored translations via their tag.
   */
  public function testSavingTranslationSettingsDropsStoredTranslations(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The translation was stored.');

    // config:ai_translate.settings is one of the tags on the entry, and
    // Config::save() invalidates config:<name> on every save.
    $this->config('ai_translate.settings')->set('cache_translations', TRUE)->save();

    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'Saving ai_translate.settings dropped the stored translation.');
  }

  /**
   * A failed translation is not stored.
   */
  public function testAnEmptyTranslationIsNotCached(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $marker = CountingTranslateProvider::EMPTY_MARKER;

    $this->assertSame('', $translator->translateContent($marker, $this->german));
    $this->assertFalse($this->bin->get($this->cacheId($marker)),
      'The empty result was not written to the bin.');

    $translator->translateContent($marker, $this->german);
    $this->assertCount(2, CountingTranslateProvider::$calls,
      'The failure was retried rather than served from cache.');
  }

  /**
   * An entry holding something other than a string is re-translated.
   */
  public function testNonStringEntryIsReTranslated(): void {
    $this->bin->set($this->cacheId('Our services'), ['not', 'a', 'string']);

    $translator = \Drupal::service('ai_translate.text_translator');

    $this->assertSame('DE: Our services',
      $translator->translateContent('Our services', $this->german),
      'A non-string entry was ignored and the provider produced a translation.');
    $this->assertCount(1, CountingTranslateProvider::$calls,
      'The guard fell through to the provider instead of raising a TypeError.');
  }

  /**
   * A cache that cannot be read falls back to the provider.
   */
  public function testCacheReadFailureStillTranslates(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')
      ->willThrowException(new \RuntimeException('The bin cannot be read.'));

    $translator = $this->translatorWithCache($cache);

    $this->assertSame('DE: Our services',
      $translator->translateContent('Our services', $this->german),
      'A failed lookup was treated as a miss rather than as a failure.');
    $this->assertCount(1, CountingTranslateProvider::$calls,
      'The provider produced the translation the cache could not serve.');
  }

  /**
   * A cache that cannot be written still returns the translation.
   */
  public function testCacheWriteFailureStillReturnsTheTranslation(): void {
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturn(FALSE);
    $cache->method('set')
      ->willThrowException(new \RuntimeException('The bin cannot be written.'));

    $translator = $this->translatorWithCache($cache);

    $this->assertSame('DE: Our services',
      $translator->translateContent('Our services', $this->german),
      'A failed write did not turn a paid-for translation into an exception.');
  }

  /**
   * Builds a translator on a given cache bin, real services otherwise.
   *
   * Everything except the bin comes from the container, so the provider call
   * behaves as it does in the rest of this class.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The bin to hand the translator.
   *
   * @return \Drupal\ai_translate\TextTranslator
   *   The translator under test.
   */
  protected function translatorWithCache(CacheBackendInterface $cache): TextTranslator {
    return new TextTranslator(
      \Drupal::entityTypeManager(),
      \Drupal::languageManager(),
      \Drupal::configFactory(),
      \Drupal::service('ai.provider'),
      \Drupal::service('twig'),
      \Drupal::service('module_handler'),
      $cache,
    );
  }

  /**
   * Editing the site default prompt drops the translations that used it.
   */
  public function testEditingTheDefaultPromptDropsStoredTranslations(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The translation was stored.');

    $prompt = \Drupal::entityTypeManager()->getStorage('ai_prompt')
      ->load($this->config('ai_translate.settings')->get('prompt'));
    $prompt->set('prompt', $prompt->get('prompt') . ' Be concise.')->save();

    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'Editing the prompt body dropped the translation it produced.');
  }

  /**
   * A language-specific prompt is tagged, and so is its fallback default.
   */
  public function testTheLanguageSpecificPromptIsTagged(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('ai_prompt');
    $storage->create([
      'id' => 'german',
      'label' => 'German prompt',
      'type' => 'ai_translate',
      'prompt' => 'Translate {inputText} into {destLangName}.',
    ])->save();

    $this->config('ai_translate.settings')
      ->set('language_settings.de.prompt', 'ai_translate__german')
      ->save();

    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);

    $storage->load('ai_translate__german')->set('prompt', 'Changed.')->save();
    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'Editing the German prompt dropped the German translation.');

    $translator->translateContent('Our services', $this->german);
    $default = $storage->load($this->config('ai_translate.settings')->get('prompt'));
    $default->set('prompt', $default->get('prompt') . ' Be concise.')->save();

    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'The default prompt is tagged too, because the provider falls back to it.');
  }

  /**
   * Builds the cache ID the translator is expected to use.
   *
   * Parameters follow TextTranslator::cacheId(): the text, the target language,
   * the source language, then what identifies the provider. Spelled out rather
   * than shared with the class under test, so a change to the ID format has to
   * be made here too and cannot pass unnoticed.
   *
   * @param string $text
   *   The source text.
   * @param string $to
   *   Target language ID.
   * @param string $from
   *   Source language ID, or the "auto" placeholder.
   * @param string $model
   *   Model ID, the part of the provider signature these tests vary.
   *
   * @return string
   *   The expected cache ID.
   */
  protected function cacheId(
    string $text,
    string $to = 'de',
    string $from = 'auto',
    string $model = 'counter-1',
  ): string {
    return implode(':', [
      'ai_translate',
      'ai_translate_counting',
      $model,
      $from,
      $to,
      hash('sha256', $text),
    ]);
  }

}
