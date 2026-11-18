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
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai_context\Service\AiContextSelectionFactoryInterface;
use Drupal\ai_translate\AiTranslateEntityContext;
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
   * Without ai_context the entity being translated does not split the cache.
   */
  public function testEntityContextIsIgnoredWithoutAiContext(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->assertSame('Hallo',
      $this->translator()->translateContent('Hello', $this->german, NULL,
        $this->entityContext('1')));
  }

  /**
   * With ai_context but push off, the entity does not split the cache either.
   */
  public function testEntityContextIsIgnoredWhilePushIsOff(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');

    $this->assertSame('Hallo',
      $this->translator(push: FALSE)->translateContent('Hello', $this->german,
        NULL, $this->entityContext('1')));
  }

  /**
   * While context is pushed the entity being translated is part of the key.
   */
  public function testEntityContextIsPartOfTheKeyWhilePushIsOn(): void {
    $this->requireRequestMetadata();
    $this->cache->set($this->cacheId('Hello'), 'Hallo');
    $this->cache->set(
      $this->cacheId('Hello', entity: 'node.article.2', push: TRUE),
      'Hallo, for node 2');

    $translator = $this->translator(push: TRUE);
    $this->assertSame('Hallo, for node 2',
      $translator->translateContent('Hello', $this->german, NULL,
        $this->entityContext('2')),
      'The entry stored for this entity was served.');

    $this->expectException(TranslationException::class);
    $translator->translateContent('Hello', $this->german, NULL,
      $this->entityContext('1'));
  }

  /**
   * The sharing setting keeps one entry per string while context is pushed.
   */
  public function testSharingSettingKeepsTheSharedKeyWhilePushIsOn(): void {
    $this->requireRequestMetadata();
    $this->cache->set($this->cacheId('Hello', push: TRUE), 'Hallo');

    $this->assertSame('Hallo',
      $this->translator(push: TRUE, share: TRUE)->translateContent('Hello',
        $this->german, NULL, $this->entityContext('1')));
  }

  /**
   * While context is pushed a call without an entity still shares its entry.
   */
  public function testMissingEntityContextKeepsTheSharedKeyWhilePushIsOn(): void {
    $this->requireRequestMetadata();
    $this->cache->set($this->cacheId('Hello', push: TRUE), 'Hallo');

    $this->assertSame('Hallo',
      $this->translator(push: TRUE)->translateContent('Hello', $this->german));
  }

  /**
   * Native translation keeps its original key even with CCC configured on.
   */
  public function testNativeProviderIgnoresEntityContextWhilePushIsOn(): void {
    $this->cache->set($this->cacheId('Hello'), 'Hallo');
    $this->assertSame('Hallo', $this->translator(push: TRUE, proxy: FALSE)
      ->translateContent('Hello', $this->german, NULL, $this->entityContext('1')));
  }

  /**
   * Delimiters inside custom entity identities do not collide.
   */
  public function testEntityIdentityDelimitersDoNotCollide(): void {
    $this->requireRequestMetadata();
    $this->cache->set($this->cacheId('Hello', entity: 'a%2Eb.c.1', push: TRUE), 'First entity');
    $translator = $this->translator(push: TRUE);
    $this->assertSame('First entity', $translator->translateContent('Hello', $this->german,
      NULL, ['entity_context' => ['entity_type' => 'a.b', 'bundle' => 'c', 'id' => '1']]));
    $this->expectException(TranslationException::class);
    $translator->translateContent('Hello', $this->german,
      NULL, ['entity_context' => ['entity_type' => 'a', 'bundle' => 'b.c', 'id' => '1']]);
  }

  /**
   * Metadata support decides whether configured push can split the proxy cache.
   */
  public function testRequestMetadataSupportControlsChatProxyCacheSharing(): void {
    $supported = method_exists(TranslateTextInput::class, 'setRequestMetadataValue')
      && method_exists(ChatInput::class, 'getRequestMetadataValue');
    $cid = $this->cacheId('Hello', entity: $supported ? 'node.article.1' : NULL,
      push: $supported, proxy: TRUE);
    $this->cache->set($cid, 'Hallo');
    $translator = $this->translator(push: TRUE);
    $this->assertSame('Hallo', $translator->translateContent('Hello', $this->german,
      NULL, $this->entityContext('1')));
    if (!$supported) {
      $this->assertSame('Hallo', $translator->translateContent('Hello', $this->german,
        NULL, $this->entityContext('2')), 'Without metadata, entities share the original proxy cache key.');
    }
  }

  /**
   * Context-specific key assertions need the request-metadata API.
   */
  private function requireRequestMetadata(): void {
    if (!method_exists(TranslateTextInput::class, 'setRequestMetadataValue')
      || !method_exists(ChatInput::class, 'getRequestMetadataValue')) {
      $this->markTestSkipped('This AI version does not store request metadata on inputs.');
    }
  }

  /**
   * Builds a translateContent() $context array for one node.
   *
   * @param string $id
   *   The node ID.
   *
   * @return array
   *   The $context array.
   */
  protected function entityContext(string $id): array {
    return [
      AiTranslateEntityContext::KEY => [
        'entity_type' => 'node',
        'bundle' => 'article',
        'id' => $id,
      ],
    ];
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
   * @param string|null $entity
   *   Entity segment, "type.bundle.id", present only while context is pushed.
   * @param bool $push
   *   Whether the chat proxy can receive pushed context.
   * @param bool|null $proxy
   *   Whether the provider is the chat proxy, defaults to the push flag.
   *
   * @return string
   *   The expected cache ID.
   */
  protected function cacheId(
    string $text,
    string $to = 'de',
    string $from = 'auto',
    string $model = 'counter-1',
    ?string $entity = NULL,
    bool $push = FALSE,
    ?bool $proxy = NULL,
  ): string {
    $segments = [
      'ai_translate',
      'ai_translate_counting',
      $model,
      $from,
      $to,
    ];
    if ($proxy ?? $push) {
      $segments = [
        'ai_translate', 'chat_translation', $model,
        'ai_translate_counting', $model, '', $from, $to,
      ];
    }
    if ($push) {
      $segments[] = 'context-v1';
    }
    if ($entity !== NULL) {
      $segments[] = $entity;
    }
    $segments[] = hash('sha256', $text);

    return implode(':', $segments);
  }

  /**
   * Builds a translator whose provider plugin cannot be instantiated.
   *
   * @param bool $cacheOn
   *   Value of the cache_translations setting.
   * @param string $model
   *   The default model ID for the translate_text operation.
   * @param bool|null $push
   *   NULL when ai_context is not enabled; otherwise whether AI Context
   *   reports push as allowed for translate:default.
   * @param bool $share
   *   Value of the Translate type's share_entity_cache setting.
   * @param bool|null $proxy
   *   Whether to use the chat proxy, defaults to TRUE when push is on.
   *
   * @return \Drupal\ai_translate\TextTranslator
   *   The translator under test.
   */
  protected function translator(bool $cacheOn = TRUE, string $model = 'counter-1', ?bool $push = NULL, bool $share = FALSE, ?bool $proxy = NULL): TextTranslator {
    $proxy ??= $push === TRUE;
    $configFactory = $this->getConfigFactoryStub([
      'ai.settings' => [
        'default_providers' => [
          'translate_text' => [
            'provider_id' => $proxy ? 'chat_translation' : 'ai_translate_counting',
            'model_id' => $model,
          ],
          'chat' => [
            'provider_id' => 'ai_translate_counting',
            'model_id' => $model,
          ],
        ],
      ],
      'ai_context.consumer_type_settings.translate' => [
        'share_entity_cache' => $share,
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

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')
      ->willReturnCallback(fn (string $module) => $module === 'ai_context' && $push !== NULL);
    $selectionFactory = NULL;
    if ($push !== NULL) {
      $selectionFactory = $this->createMock(AiContextSelectionFactoryInterface::class);
      $selectionFactory->method('isPushAllowed')
        ->with('translate:default')
        ->willReturn($push);
    }

    return new TextTranslator(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LanguageManagerInterface::class),
      $configFactory,
      $manager,
      $this->createMock(TwigEnvironment::class),
      $moduleHandler,
      $this->cache,
      $selectionFactory,
    );
  }

}
