<?php

namespace Drupal\ai_translate;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\TranslateText\TranslateTextInput;

/**
 * Defines text translator service.
 *
 * When the cache_translations setting is on, a translation is stored and reused
 * the next time the same text is translated by the same provider and model into
 * the same language. Deduplication inside a single run falls out of that rather
 * than being a separate mechanism: EntityTranslationOrchestrator translates one
 * field at a time, so two fields holding the same string produce the same cache
 * ID, and the first one's write lands before the second one's lookup.
 *
 * Entries live in this module's own cache bin, cache.ai_translate, injected
 * here as $cache and stored in the cache_ai_translate table on a default
 * install. Flushing that bin, or a full cache rebuild, drops every stored
 * translation. A read or write that fails is logged and never stops a
 * translation.
 *
 * Three limitations are worth knowing about.
 *
 * hook_ai_translate_translation_alter() can change the prompt or the provider
 * at call time, and a lookup that happens before the provider is built cannot
 * see that. Sites relying on such a hook should leave the setting off.
 *
 * A cache hit never reaches the provider, so neither PreGenerateResponseEvent
 * nor PostGenerateResponseEvent is dispatched and no token usage is recorded.
 * AI Observability shows no activity at all for a fully cached translation run.
 *
 * The cache ID covers the text as it arrives, markup included, so "Read more"
 * and "<p>Read more</p>" are two entries and two provider calls even though the
 * visible text is the same.
 */
class TextTranslator implements TextTranslatorInterface {

  use LoggerChannelTrait;

  /**
   * Plugin ID of the chat proxy provider this module ships.
   *
   * @see \Drupal\ai_translate\Plugin\AiProvider\ChatTranslationProvider
   */
  protected const CHAT_PROXY_PROVIDER_ID = 'chat_translation';

  /**
   * AI module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $aiConfig;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
    protected AiProviderPluginManager $aiProviderManager,
    protected TwigEnvironment $twig,
    protected ModuleHandlerInterface $moduleHandler,
    protected CacheBackendInterface $cache,
  ) {
    $this->aiConfig = $this->configFactory->get('ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function translateContent(
    string $input_text,
    LanguageInterface $langTo,
    ?LanguageInterface $langFrom = NULL,
    array $context = [],
  ) : string {
    $cid = NULL;
    $settings = $this->configFactory->get('ai_translate.settings');

    try {
      $providerConfig = $this->aiProviderManager->getDefaultProviderForOperationType('translate_text');

      // The provider and the model are part of the cache ID, so both have to be
      // resolved before the lookup. This only reads ai.settings; the provider
      // plugin itself is not built unless the lookup misses.
      if (!empty($providerConfig) && $settings->get('cache_translations')) {
        // @todo Include $context in the key once a caller uses it for provider
        // selection.
        $cid = $this->cacheId($input_text, $langTo, $langFrom, $providerConfig);
        $cached = $this->cacheGet($cid);
        if ($cached !== NULL) {
          return $cached;
        }
      }

      /** @var \Drupal\ai\OperationType\TranslateText\TranslateTextInterface $provider */
      $provider = $this->aiProviderManager->createInstance($providerConfig['provider_id'], $providerConfig);
      $translation = $provider->translateText(
        new TranslateTextInput($input_text, $langFrom?->getId(), $langTo->getId()),
        $providerConfig['model_id']
      );

      $cleaned = str_replace('```html', '', $translation->getNormalized());
      $cleaned = trim(trim($cleaned, '```'), ' ');
      $cleaned = trim($cleaned, '"');
    }
    catch (\Throwable $e) {
      $this->getLogger('ai_translate')->warning($e->getMessage());
      throw new TranslationException($e->getMessage());
    }

    // An empty string means the call failed rather than produced nothing:
    // ChatTranslationProvider returns one when the target language cannot be
    // resolved. Storing it at CACHE_PERMANENT would pin the failure and make
    // translating again return the same empty result, so leave it uncached.
    if ($cid !== NULL && $cleaned !== '') {
      $this->cacheSet($cid, $cleaned, $langTo, $settings);
    }

    return $cleaned;
  }

  /**
   * Reads one stored translation, treating an unusable cache as a miss.
   *
   * The read is guarded here rather than left to the caller's try block. That
   * block turns anything it catches into a TranslationException, so a cache
   * backend that cannot be read would fail a translation this module is still
   * able to produce. Falling through to the provider costs one request.
   *
   * @param string $cid
   *   The cache ID, from cacheId().
   *
   * @return string|null
   *   The stored translation, or NULL when there is nothing usable to serve.
   */
  protected function cacheGet(string $cid) : ?string {
    try {
      $cached = $this->cache->get($cid);
    }
    catch (\Throwable $e) {
      $this->getLogger('ai_translate')->warning(
        'Could not read the translation cache, translating instead: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }

    // An entry holding anything other than a string is not a translation.
    if ($cached !== FALSE && is_string($cached->data)) {
      return $cached->data;
    }

    return NULL;
  }

  /**
   * Stores one translation, keeping the result if the write fails.
   *
   * A failed write costs one provider request the next time the same text is
   * translated, and nothing else. Letting it surface would throw away a
   * translation that has already been paid for, so it is logged instead.
   *
   * @param string $cid
   *   The cache ID, from cacheId().
   * @param string $translation
   *   The cleaned translation to store.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language, which decides the prompt tags.
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   This module's settings, already read by the caller.
   */
  protected function cacheSet(
    string $cid,
    string $translation,
    LanguageInterface $langTo,
    ImmutableConfig $settings,
  ) : void {
    try {
      $this->cache->set($cid, $translation, CacheBackendInterface::CACHE_PERMANENT,
        $this->cacheTags($langTo, $settings));
    }
    catch (\Throwable $e) {
      $this->getLogger('ai_translate')->warning(
        'Could not store the translation in the cache: @message',
        ['@message' => $e->getMessage()]
      );
    }
  }

  /**
   * Builds the cache ID for one string, provider, model and language pair.
   *
   * The provider and model are in the ID rather than covered by a cache tag on
   * ai.settings. That file holds the defaults for every AI operation type, so a
   * tag on it cannot tell a translation-model change from an unrelated one and
   * dropped every stored translation whenever any AI default was saved.
   *
   * Which provider and model those are is not always what the translate_text
   * default says. See providerSignature().
   *
   * @param string $input_text
   *   The text that would be sent to the provider.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language.
   * @param \Drupal\Core\Language\LanguageInterface|null $langFrom
   *   Optional source language.
   * @param array $providerConfig
   *   The resolved default provider for the translate_text operation, from
   *   AiProviderPluginManager::getDefaultProviderForOperationType().
   *
   * @return string
   *   The cache ID.
   */
  protected function cacheId(
    string $input_text,
    LanguageInterface $langTo,
    ?LanguageInterface $langFrom,
    array $providerConfig,
  ) : string {
    return implode(':', array_merge(
      ['ai_translate'],
      $this->providerSignature($providerConfig),
      [
        $langFrom?->getId() ?? 'auto',
        $langTo->getId(),
        hash('sha256', $input_text),
      ]
    ));
  }

  /**
   * Names the provider and model that will really produce the translation.
   *
   * The translate_text default is not always the whole answer. This module's
   * own chat_translation provider is a proxy:
   * ChatTranslationProvider::translateText() discards the model_id it is
   * handed and runs the chat operation with whatever default_providers.chat
   * names. That is the model whose output gets cached, so keying on
   * translate_text alone kept serving one model's translations after the site
   * had switched to another.
   *
   * Both lookups are plain ai.settings reads. No provider plugin is built here.
   *
   * @param array $providerConfig
   *   The resolved default provider for the translate_text operation, from
   *   AiProviderPluginManager::getDefaultProviderForOperationType().
   *
   * @return string[]
   *   Provider and model IDs, outermost first, for use in the cache ID.
   */
  protected function providerSignature(array $providerConfig) : array {
    $signature = [
      $providerConfig['provider_id'] ?? '',
      $providerConfig['model_id'] ?? '',
    ];

    if (($providerConfig['provider_id'] ?? '') === static::CHAT_PROXY_PROVIDER_ID) {
      $chatConfig = $this->aiProviderManager
        ->getDefaultProviderForOperationType('chat') ?? [];
      $signature[] = $chatConfig['provider_id'] ?? '';
      $signature[] = $chatConfig['model_id'] ?? '';
    }

    return $signature;
  }

  /**
   * Builds the tags that drop entries when translation settings change.
   *
   * Prompt resolution mirrors ChatTranslationProvider::translateText(): a
   * language-specific prompt if one is configured, otherwise the site default.
   * Both are tagged, because the provider falls back to the default when the
   * language-specific prompt entity turns out to be empty. Editing a prompt
   * does not change its ID, so a tag is the only thing that can catch it.
   *
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   Destination language, which decides the prompt.
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   This module's settings, already read by the caller.
   *
   * @return string[]
   *   Cache tags for one entry.
   */
  protected function cacheTags(LanguageInterface $langTo, ImmutableConfig $settings) : array {
    $tags = ['config:ai_translate.settings'];

    $promptIds = [
      $settings->get('language_settings.' . $langTo->getId() . '.prompt'),
      $settings->get('prompt'),
    ];
    foreach (array_unique(array_filter($promptIds)) as $promptId) {
      $tags[] = 'config:ai.ai_prompt.' . $promptId;
    }

    return $tags;
  }

}
