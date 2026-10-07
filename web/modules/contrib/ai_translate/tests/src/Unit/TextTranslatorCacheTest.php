<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai_translate\TextTranslator;
use Drupal\ai_translate\TranslationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests the cache lookup side of the text translator.
 *
 * AiProviderPluginManager is final, so it cannot be mocked. A real one is built
 * instead, with an empty namespace list, which makes createInstance() throw.
 * The translator turns that into a TranslationException, so throwing means the
 * code went to the provider and a returned string means it was served from
 * cache.
 *
 * The storing side needs a provider call that succeeds and is covered by
 * \Drupal\Tests\ai_translate\Kernel\TranslationCacheTest.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[CoversClass(TextTranslator::class)]
class TextTranslatorCacheTest extends UnitTestCase {

  /**
   * The cache bin the translator reads and writes.
   */
  protected MemoryBackend $cache;

  /**
   * Source language used throughout.
   */
  protected LanguageInterface $english;

  /**
   * Target language used throughout.
   */
  protected LanguageInterface $german;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->cache = new MemoryBackend($this->createMock(TimeInterface::class));
    $this->english = new Language(['id' => 'en', 'name' => 'English']);
    $this->german = new Language(['id' => 'de', 'name' => 'German']);
  }

  /**
   * A cache hit is served without the provider being built.
   */
  public function testCacheHitIsServedWithoutReachingTheProvider(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->assertSame('Hallo',
      $this->translator()->translateContent('Hello', $this->german));
  }

  /**
   * A cache miss goes to the provider.
   */
  public function testCacheMissReachesTheProvider(): void {
    $this->expectException(TranslationException::class);
    $this->translator()->translateContent('Hello', $this->german);
  }

  /**
   * A stored translation is not reused after the model changes.
   */
  public function testTheModelIsPartOfTheKey(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->expectException(TranslationException::class);
    $this->translator(TRUE, 'counter-2')->translateContent('Hello', $this->german);
  }

  /**
   * A stored translation is not reused for another target language.
   */
  public function testTheTargetLanguageIsPartOfTheKey(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->expectException(TranslationException::class);
    $this->translator()->translateContent('Hello',
      new Language(['id' => 'fr', 'name' => 'French']));
  }

  /**
   * A named source language is keyed separately from an unspecified one.
   */
  public function testTheSourceLanguageIsPartOfTheKey(): void {
    $this->cache->set($this->cacheId('Hello', 'de', 'en'), 'Hallo, named source');

    $this->assertSame('Hallo, named source',
      $this->translator()->translateContent('Hello', $this->german, $this->english));
  }

  /**
   * Editing the source text misses the cache.
   */
  public function testEditedTextMissesTheCache(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->expectException(TranslationException::class);
    $this->translator()->translateContent('Hello there', $this->german);
  }

  /**
   * With the setting off the cache is not consulted at all.
   */
  public function testDisablingTheSettingBypassesTheCache(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->expectException(TranslationException::class);
    $this->translator(FALSE)->translateContent('Hello', $this->german);
  }

  /**
   * An entry holding something other than a string is treated as a miss.
   */
  public function testNonStringEntryIsIgnored(): void {
    $this->cache->set($this->cacheId('Hello'), ['not', 'a', 'string']);

    $this->expectException(TranslationException::class);
    $this->translator()->translateContent('Hello', $this->german);
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

  /**
   * Builds a translator whose provider plugin cannot be instantiated.
   *
   * @param bool $cacheOn
   *   Value of the cache_translations setting.
   * @param string $model
   *   The default model ID for the translate_text operation.
   *
   * @return \Drupal\ai_translate\TextTranslator
   *   The translator under test.
   */
  protected function translator(bool $cacheOn = TRUE, string $model = 'counter-1'): TextTranslator {
    $configFactory = $this->getConfigFactoryStub([
      'ai.settings' => [
        'default_providers' => [
          'translate_text' => [
            'provider_id' => 'ai_translate_counting',
            'model_id' => $model,
          ],
        ],
      ],
      'ai_translate.settings' => [
        'cache_translations' => $cacheOn,
        'prompt' => 'translation',
        'language_settings' => [],
      ],
    ]);

    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')
      ->willReturn($this->createMock(LoggerChannelInterface::class));

    // LoggerChannelTrait::getLogger() reaches into \Drupal, and the provider
    // manager pulls three services out of the container it is handed.
    $container = new ContainerBuilder();
    $container->set('config.factory', $configFactory);
    $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
    $container->set('logger.factory', $loggerFactory);
    \Drupal::setContainer($container);

    $manager = new AiProviderPluginManager(
      new \ArrayIterator([]),
      new MemoryBackend($this->createMock(TimeInterface::class)),
      $this->createMock(ModuleHandlerInterface::class),
      $container,
      $this->createMock(MessengerInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(HostnameFilter::class),
    );

    return new TextTranslator(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $configFactory,
      $manager,
      $this->createMock(TwigEnvironment::class),
      $this->createMock(ModuleHandlerInterface::class),
      $this->cache,
    );
  }

}
