<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_translate_test\Plugin\AiProvider\CountingTranslateProvider;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;
use Drupal\ai_context\Entity\AiContextItem;
use Drupal\ai_context\Service\AiContextScopeIndexService;
use Drupal\ai_context\Form\AiContextConsumerTypeSettingsForm;
use Drupal\ai_translate\AiTranslateEntityContext;
use Drupal\ai_translate\Plugin\AiContextConsumerType\AiContextConsumerTypeTranslate;
use Drupal\Core\Form\FormState;
use Drupal\ai_translate_test\Plugin\AiProvider\CountingChatProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests translation caching when AI Context can push entity-scoped context.
 *
 * While push is on for translate:default the Context Control Center may push
 * different items for different entities, so a cached translation must not be
 * reused across entities, and it must be dropped when the items or the site
 * settings change. While push is off, the default, the cache behaves as it
 * does without ai_context. A counting chat provider exercises the real proxy
 * path without sending paid requests.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
final class TranslationCacheEntityContextTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'text',
    'filter',
    'options',
    'taxonomy',
    'key',
    'language',
    'content_translation',
    'content_moderation',
    'workflows',
    'views',
    'ai',
    'ai_agents',
    'ai_context',
    'ai_translate',
    'ai_translate_test',
  ];

  /**
   * The dedicated translation cache bin.
   */
  private CacheBackendInterface $bin;

  /**
   * Target language used throughout.
   */
  private Language $german;

  /**
   * Whether this test configured push on the chat proxy.
   */
  private bool $push = FALSE;

  /**
   * Whether this AI version can pass context through the proxy inputs.
   */
  private bool $metadataSupported;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->metadataSupported = method_exists(TranslateTextInput::class, 'setRequestMetadataValue')
      && method_exists(ChatInput::class, 'getRequestMetadataValue');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('ai_context_item');
    $this->installEntitySchema('ai_context_usage');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('ai_context', [AiContextScopeIndexService::TABLE]);
    $this->installConfig([
      'system',
      'field',
      'file',
      'filter',
      'taxonomy',
      'ai',
      'ai_context',
      'ai_translate',
    ]);

    // There is no install config for the Translate type settings. Core only
    // invalidates config:<name> when an existing object is saved, so seed
    // the object like a site where the type settings were saved once.
    $this->config(AiContextConsumerTypeTranslate::TYPE_SETTINGS_CONFIG)
      ->set('enabled', TRUE)
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

    $this->installEntitySchema('configurable_language');
    ConfigurableLanguage::createFromLangcode('de')->save();
    CountingChatProvider::$calls = [];
    $this->bin = \Drupal::service('cache.ai_translate');
    $this->german = new Language(['id' => 'de', 'name' => 'German']);
  }

  /**
   * While push is off, the default, one string on two entities is shared.
   */
  public function testWhilePushIsOffTheEntryIsSharedAcrossEntities(): void {
    $translator = \Drupal::service('ai_translate.text_translator');

    $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('1'));
    $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('2'));

    $this->assertCount(1, CountingChatProvider::$calls,
      'Nothing is pushed, so the second entity reused the first translation.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entry uses the ID without an entity segment.');
    $this->assertFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'No entity-keyed entry was written.');
  }

  /**
   * Turning push on drops entries that were stored while it was off.
   */
  public function testTurningPushOnDropsEntriesStoredWhilePushWasOff(): void {
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The translation was stored while push was off.');

    $cid = $this->cacheId('Our services');
    $this->enablePush();

    $this->assertFalse($this->bin->get($cid),
      'The entry made without pushed context was dropped.');
  }

  /**
   * One string on two entities costs two provider calls, then none.
   */
  public function testTheSameStringIsTranslatedPerEntity(): void {
    $this->requireRequestMetadata();
    $this->enablePush();
    $translator = \Drupal::service('ai_translate.text_translator');

    $first = $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('1'));
    $second = $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('2'));
    $again = $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('1'));

    $this->assertSame('translated by chat-1', $first);
    $this->assertSame('translated by chat-1', $second);
    $this->assertSame($first, $again);
    $this->assertSame(['chat-1', 'chat-1'],
      CountingChatProvider::$calls,
      'Each entity reached the provider once; the repeat was served.');
    $this->assertNotFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'The first entity has an entry of its own.');
    $this->assertNotFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.2')),
      'The second entity has an entry of its own.');
    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'No entity-less entry was written.');
  }

  /**
   * The sharing setting keeps one entry per string while context is pushed.
   */
  public function testSharingSettingKeepsOneEntryAcrossEntities(): void {
    $this->requireRequestMetadata();
    $this->enablePush();
    $this->enableSharing();
    $translator = \Drupal::service('ai_translate.text_translator');

    $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('1'));
    $translator->translateContent('Our services', $this->german,
      NULL, $this->entityContext('2'));

    $this->assertCount(1, CountingChatProvider::$calls,
      'The site opted into one translation per string.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entry uses the ID without an entity segment.');
    $this->assertSame(
      [
        'ai_context_item_list',
        'config:ai.ai_prompt.ai_translate__ai_translate_default',
        'config:ai_context.consumer_type_settings.translate',
        'config:ai_context.consumers',
        'config:ai_context.settings',
        'config:ai_translate.settings',
      ],
      $this->bin->get($this->cacheId('Our services'))->tags,
      'Sharing keeps the AI Context tags, so item changes still drop it.');
  }

  /**
   * The Translate type settings form offers the option and notes push state.
   */
  public function testTypeSettingsFormOffersSharingAndNotesPushState(): void {
    $key = AiContextConsumerTypeTranslate::SHARE_ENTITY_CACHE;
    $form = \Drupal::formBuilder()->getForm(
      AiContextConsumerTypeSettingsForm::class,
      AiContextConsumerTypeTranslate::PLUGIN_ID,
    );
    $this->assertArrayHasKey('enabled', $form);
    $this->assertArrayHasKey($key, $form,
      'The option sits on the Translate type settings form.');
    $this->assertFalse($form[$key]['#default_value'],
      'Off until a site builder opts in.');
    $this->assertStringContainsString('currently off',
      (string) $form[$key]['#description'],
      'With push off the description says the setting does nothing yet.');

    $this->enablePush();
    $this->enableSharing();
    $form = \Drupal::formBuilder()->getForm(
      AiContextConsumerTypeSettingsForm::class,
      AiContextConsumerTypeTranslate::PLUGIN_ID,
    );
    $this->assertTrue($form[$key]['#default_value']);
    $this->assertStringNotContainsString('currently off',
      (string) $form[$key]['#description'],
      'With push on the note is gone.');
  }

  /**
   * Submitting the type form stores the option only while it is checked.
   */
  public function testTypeSettingsFormStoresSharingOnlyWhenChecked(): void {
    $key = AiContextConsumerTypeTranslate::SHARE_ENTITY_CACHE;
    $plugin = \Drupal::service('plugin.manager.ai_context_consumer_type')
      ->createInstance(AiContextConsumerTypeTranslate::PLUGIN_ID);
    $form = [];

    $state = (new FormState())->setValues(['enabled' => TRUE, $key => TRUE]);
    $this->assertSame(
      ['enabled' => TRUE, $key => TRUE],
      $plugin->submitTypeSettingsForm($form, $state),
    );

    $state = (new FormState())->setValues(['enabled' => TRUE, $key => FALSE]);
    $this->assertSame(
      ['enabled' => TRUE, $key => NULL],
      $plugin->submitTypeSettingsForm($form, $state),
      'NULL clears the key so off is not exported.');
  }

  /**
   * Turning sharing on drops entries that were keyed per entity.
   */
  public function testTurningSharingOnDropsEntityKeyedEntries(): void {
    $this->translateAndAssertStored();

    $this->enableSharing();

    $this->assertFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'The per-entity entry was dropped with the rest of the bin.');
  }

  /**
   * A call without an entity keeps the shared entry.
   */
  public function testWithoutEntityContextTheEntryIsShared(): void {
    $this->enablePush();
    $translator = \Drupal::service('ai_translate.text_translator');

    $translator->translateContent('Our services', $this->german);
    $translator->translateContent('Our services', $this->german);

    $this->assertCount(1, CountingChatProvider::$calls,
      'Interface translation and Drush text calls still share one entry.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entry uses the ID without an entity segment.');
  }

  /**
   * Saving a context item invalidates cached translations.
   */
  public function testSavingOneContextItemDropsStoredTranslations(): void {
    $this->translateAndAssertStored();

    AiContextItem::create([
      'label' => 'House style',
      'status' => 1,
      'content' => 'The house style word is zorble-widget.',
    ])->save();

    $this->assertFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'A new context item invalidated the cached translation.');
    $this->assertFalse($this->bin->get($this->cacheId('Our services')),
      'The entity-less entry is dropped too; global items push everywhere.');
  }

  /**
   * Saving the consumers configuration invalidates cached translations.
   */
  public function testSavingConsumersConfigDropsStoredTranslations(): void {
    $this->translateAndAssertStored();

    $cid = $this->cacheId('Our services', 'node.article.1');
    $this->config('ai_context.consumers')
      ->set('consumers', [
        [
          'consumer_id' => 'translate:default',
          'type' => 'translate',
          'push_enabled' => FALSE,
        ],
      ])
      ->save();

    $this->assertFalse(
      $this->bin->get($cid),
      'Turning push off for the consumer invalidated the cached translation.');
  }

  /**
   * Disabling the Translate consumer type invalidates cached translations.
   */
  public function testDisablingTheTypeDropsStoredTranslations(): void {
    $this->translateAndAssertStored();

    $cid = $this->cacheId('Our services', 'node.article.1');
    $this->config(AiContextConsumerTypeTranslate::TYPE_SETTINGS_CONFIG)
      ->set('enabled', FALSE)
      ->save();

    $this->assertFalse(
      $this->bin->get($cid),
      'Disabling the type invalidated the cached translation.');
  }

  /**
   * Saving the AI Context settings invalidates cached translations.
   */
  public function testSavingAiContextSettingsDropsStoredTranslations(): void {
    $this->translateAndAssertStored();

    $this->config('ai_context.settings')
      ->set('selection_mode', 'minimal')
      ->save();

    $this->assertFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'Changing the site selection mode invalidated the cached translation.');
  }

  /**
   * Native translators keep cross-entity reuse even when CCC push is enabled.
   */
  public function testNativeProviderDoesNotSplitCacheWhilePushIsEnabled(): void {
    $this->enablePush();
    $this->config('ai.settings')->set('default_providers.translate_text', [
      'provider_id' => 'ai_translate_counting',
      'model_id' => 'counter-1',
    ])->save();
    CountingTranslateProvider::$calls = [];
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german, NULL, $this->entityContext('1'));
    $translator->translateContent('Our services', $this->german, NULL, $this->entityContext('2'));
    $this->assertCount(1, CountingTranslateProvider::$calls);
    $cid = 'ai_translate:ai_translate_counting:counter-1:auto:de:' . hash('sha256', 'Our services');
    $entry = $this->bin->get($cid);
    $this->assertNotFalse($entry);
    $this->assertNotContains('ai_context_item_list', $entry->tags);
    $this->assertNotContains('config:ai_context.settings', $entry->tags);
  }

  /**
   * Legacy entries without CCC tags cannot bypass push on entity-less calls.
   */
  public function testPushDoesNotReuseUntaggedLegacyEntriesWithoutAnEntity(): void {
    $cid = $this->cacheId('Our services');
    $this->bin->set($cid, 'Legacy translation', CacheBackendInterface::CACHE_PERMANENT,
      ['config:ai_translate.settings']);
    $this->enablePush();
    $this->assertNotFalse($this->bin->get($cid), 'The legacy entry survived config invalidation.');
    $translator = \Drupal::service('ai_translate.text_translator');
    $this->assertSame($this->metadataSupported ? 'translated by chat-1' : 'Legacy translation',
      $translator->translateContent('Our services', $this->german));
    $this->assertCount($this->metadataSupported ? 1 : 0, CountingChatProvider::$calls);
  }

  /**
   * Entity-less shared keys still need invalidation when context changes.
   */
  public function testSharingToggleInvalidatesTranslationsWithoutAnEntityWithTheSameKey(): void {
    $this->enablePush();
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german);
    $cid = $this->cacheId('Our services');
    $this->enableSharing();
    $this->assertSame($cid, $this->cacheId('Our services'));
    $this->assertFalse($this->bin->get($cid));
    $translator->translateContent('Our services', $this->german);
    $this->assertCount(2, CountingChatProvider::$calls);
  }

  /**
   * Context-specific key and tag assertions need the request-metadata API.
   */
  private function requireRequestMetadata(): void {
    if (!$this->metadataSupported) {
      $this->markTestSkipped('This AI version does not store request metadata on inputs.');
    }
  }

  /**
   * Turns push on for translate:default.
   *
   * The consumers config is installed in setUp(), so this save also
   * invalidates config:ai_context.consumers like an admin save would.
   */
  private function enablePush(): void {
    $this->push = TRUE;
    $this->config('ai_context.consumers')
      ->set('consumers', [
        [
          'consumer_id' => 'translate:default',
          'type' => 'translate',
          'push_enabled' => TRUE,
        ],
      ])
      ->save();
  }

  /**
   * Opts into one cached translation per string while pushing.
   */
  private function enableSharing(): void {
    $this->config(AiContextConsumerTypeTranslate::TYPE_SETTINGS_CONFIG)
      ->set(AiContextConsumerTypeTranslate::SHARE_ENTITY_CACHE, TRUE)
      ->save();
  }

  /**
   * Turns push on, translates one string for node 1 and without an entity.
   */
  private function translateAndAssertStored(): void {
    $this->requireRequestMetadata();
    $this->enablePush();
    $translator = \Drupal::service('ai_translate.text_translator');
    $translator->translateContent('Our services', $this->german, NULL,
      $this->entityContext('1'));
    $translator->translateContent('Our services', $this->german);

    $this->assertNotFalse(
      $this->bin->get($this->cacheId('Our services', 'node.article.1')),
      'The entity-scoped translation was stored.');
    $this->assertNotFalse($this->bin->get($this->cacheId('Our services')),
      'The entity-less translation was stored.');
  }

  /**
   * Builds a translateContent() $context array for one article node.
   *
   * @param string $id
   *   The node ID.
   *
   * @return array
   *   The $context array.
   */
  private function entityContext(string $id): array {
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
   * Spelled out rather than shared with the class under test, so a change to
   * the ID format has to be made here too and cannot pass unnoticed.
   *
   * @param string $text
   *   The source text.
   * @param string|null $entity
   *   Entity segment, "type.bundle.id", or NULL for a call with no entity.
   *
   * @return string
   *   The expected cache ID.
   */
  private function cacheId(string $text, ?string $entity = NULL): string {
    $segments = ['ai_translate', 'chat_translation', 'chat-1', 'ai_translate_counting_chat', 'chat-1', '', 'auto', 'de'];
    if ($this->push && $this->metadataSupported) {
      $segments[] = 'context-v1';
    }
    if ($entity !== NULL) {
      $segments[] = $entity;
    }
    $segments[] = hash('sha256', $text);

    return implode(':', $segments);
  }

}
