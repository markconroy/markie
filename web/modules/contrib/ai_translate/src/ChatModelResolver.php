<?php

namespace Drupal\ai_translate;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decides which chat provider and model actually translate.
 *
 * The answer is not simply the site's default chat provider: a target language
 * can name its own provider and model, and the model selected for the
 * translate_text operation overrides the chat default. Anything that needs to
 * know which model will run - the chat proxy before it sends, and any caller
 * that has to name that model - has to ask the same question, so it is answered
 * here once rather than in each of them.
 */
class ChatModelResolver {

  /**
   * How long, in seconds, a model check is reused across requests.
   *
   * Tags catch a change to the AI settings or to the provider's own
   * configuration, but not a model the provider withdraws on its side. An hour
   * bounds how long such a model keeps being picked, and how long a newly
   * offered one keeps being passed over.
   */
  protected const OFFERED_TTL = 3600;

  /**
   * Whether a provider offers a model, keyed by provider ID and then model ID.
   *
   * @var array<string, array<string, bool>>
   */
  protected array $offered = [];

  /**
   * Constructs a ChatModelResolver object.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderManager
   *   The AI provider plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The ai_translate cache bin, which keeps model checks across requests.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    #[Autowire(service: 'ai.provider')]
    protected AiProviderPluginManager $aiProviderManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    #[Autowire(service: 'cache.ai_translate')]
    protected CacheBackendInterface $cache,
    protected TimeInterface $time,
  ) {}

  /**
   * Resolves the provider and model that will perform a translation.
   *
   * Most specific wins:
   * 1. The provider and model set for this target language in
   *    ai_translate.settings:language_settings.<langcode>.model, stored by the
   *    settings form as "provider_id__model_id".
   * 2. The model selected for the translate_text operation, run on the default
   *    chat provider.
   * 3. The default chat provider and model.
   *
   * Each candidate is checked against the models the provider actually offers.
   * A selection made before the site's providers changed no longer matches
   * anything, and passing it on would send a model ID to a provider that has
   * never heard of it, so it is discarded in favor of the next candidate.
   *
   * @param string $requestedModelId
   *   What the caller asked for: the model selected for the translate_text
   *   operation, which the AI module hands to every provider fulfilling it.
   *   This is an input to the decision, not its result - the model that ends
   *   up running is in the returned selection. An empty string means the
   *   caller expressed no preference, which is how the AI module's own
   *   contract spells "not set" for this value.
   * @param \Drupal\Core\Language\LanguageInterface|null $langTo
   *   Destination language, or NULL to skip step 1.
   *
   * @return \Drupal\ai_translate\ChatModelSelection|null
   *   The provider and model that will translate, or NULL when there is none:
   *   the site has no default chat provider, and no usable one is set for the
   *   target language either.
   */
  public function resolve(string $requestedModelId = '', ?LanguageInterface $langTo = NULL): ?ChatModelSelection {
    // The site-wide chat default is the starting point. Each step below
    // refines it, and it is also the answer when none of them apply. It is
    // empty on a site with no chat default at all, which only step 1 can
    // still produce a usable selection from.
    $chatDefault = $this->aiProviderManager->getDefaultProviderForOperationType('chat') ?? [];
    $providerId = (string) ($chatDefault['provider_id'] ?? '');
    $modelId = (string) ($chatDefault['model_id'] ?? '');

    // Step 1: a provider and model picked for this particular target language.
    // Most sites configure none, in which case there is nothing to read and
    // the next step decides. The value is stored as "provider_id__model_id",
    // so it can move the translation to an entirely different provider rather
    // than only to another model on the default one.
    if ($langTo !== NULL) {
      $languageModel = $this->configFactory->get('ai_translate.settings')
        ->get('language_settings.' . $langTo->getId() . '.model');

      // The settings form writes either nothing or a "provider_id__model_id"
      // pair, so anything else arrived through a config import or a
      // settings.php override. Without this it would be passed over as
      // quietly as no value at all, while the form goes on showing a model as
      // selected for the language - the same mismatch the stale case below is
      // logged for.
      if (!empty($languageModel) && !str_contains($languageModel, '__')) {
        $this->loggerFactory->get('ai_translate')->warning(
          'Ignoring the model configured for @langcode: @value is not a "provider_id__model_id" pair.',
          [
            '@langcode' => $langTo->getId(),
            '@value' => $languageModel,
          ]);
      }
      elseif (!empty($languageModel)) {
        [$languageProviderId, $languageModelId] = explode('__', $languageModel, 2);

        // Nothing is more specific than a choice made for this language, so
        // it wins outright and what the caller asked for is not consulted.
        if ($this->modelIsOffered($languageProviderId, $languageModelId)) {
          return new ChatModelSelection($languageProviderId, $languageModelId);
        }

        // Configured, but the provider has stopped offering it since. Say so
        // rather than failing: the next step is a reasonable substitute, and
        // silently translating with an unannounced model is worse than
        // translating with the default one.
        $this->loggerFactory->get('ai_translate')->warning(
          'Ignoring the model configured for @langcode: @provider no longer offers @model.',
          [
            '@langcode' => $langTo->getId(),
            '@provider' => $languageProviderId,
            '@model' => $languageModelId,
          ]);
      }
    }

    // Step 2: the model the caller asked for, run on the default chat
    // provider. Only the model is taken from the request; the provider stays
    // the chat default, because the caller selected that model for the
    // translate_text operation without naming a chat provider to run it on.
    if ($requestedModelId !== '' && $providerId !== '') {
      if ($this->modelIsOffered($providerId, $requestedModelId)) {
        $modelId = $requestedModelId;
      }
      else {
        // Same reasoning as above: fall through to the default model, having
        // recorded why the selected one was not used.
        $this->loggerFactory->get('ai_translate')->warning(
          'Ignoring the model selected for the translation operation: @provider no longer offers @model.',
          [
            '@provider' => $providerId,
            '@model' => $requestedModelId,
          ]);
      }
    }

    // Step 3: nothing more specific applied, so the chat default stands -
    // unless there is none.
    if ($providerId === '') {
      return NULL;
    }
    return new ChatModelSelection($providerId, $modelId);
  }

  /**
   * Checks whether a provider still offers a model for the chat operation.
   *
   * Asking means calling getConfiguredModels(), which some providers answer
   * from a remote API on every call. The resolver runs before every
   * translation, and each Batch step is a request of its own, so the answer is
   * kept in the cache bin for OFFERED_TTL as well as for the rest of the
   * request.
   *
   * @param string $provider_id
   *   The provider plugin ID.
   * @param string $model_id
   *   The model ID.
   *
   * @return bool
   *   TRUE when the provider exists, can be asked, and lists the model.
   */
  protected function modelIsOffered(string $provider_id, string $model_id): bool {
    if (isset($this->offered[$provider_id][$model_id])) {
      return $this->offered[$provider_id][$model_id];
    }

    $cid = 'ai_translate_model_offered:' . $provider_id . ':' . $model_id;
    $cached = $this->cacheGet($cid);
    if ($cached !== NULL) {
      return $this->offered[$provider_id][$model_id] = $cached;
    }

    try {
      $provider = $this->aiProviderManager->createInstance($provider_id);
      $offered = in_array('chat', $provider->getSupportedOperationTypes(), TRUE)
        && array_key_exists($model_id, $provider->getConfiguredModels('chat'));
    }
    catch (\Exception $e) {
      // getConfiguredModels() asks the provider itself, so a missing key or an
      // unreachable model list throws rather than returning nothing.
      // AiProviderPluginManager::getSimpleProviderModelOptions() catches the
      // same call when it builds the list this checks against. Treating it as
      // "not offered" falls through to the next candidate, which is the point
      // of asking. It is not stored: a provider that is down for a minute
      // should not lose its model for an hour.
      $this->loggerFactory->get('ai_translate')->warning(
        'Could not check whether @provider offers @model: @error',
        [
          '@provider' => $provider_id,
          '@model' => $model_id,
          '@error' => $e->getMessage(),
        ]);
      return $this->offered[$provider_id][$model_id] = FALSE;
    }

    $this->cacheSet($cid, $offered, $provider);
    return $this->offered[$provider_id][$model_id] = $offered;
  }

  /**
   * Reads a stored model check, treating an unusable cache as a miss.
   *
   * @param string $cid
   *   The cache ID.
   *
   * @return bool|null
   *   The stored answer, or NULL when there is nothing usable to serve.
   */
  protected function cacheGet(string $cid): ?bool {
    try {
      $cached = $this->cache->get($cid);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_translate')->warning(
        'Could not read the model check from the cache, asking the provider instead: @message',
        ['@message' => $e->getMessage()]
      );
      return NULL;
    }

    if ($cached !== FALSE && is_bool($cached->data)) {
      return $cached->data;
    }

    return NULL;
  }

  /**
   * Stores a model check, keeping the answer if the write fails.
   *
   * The entry is tagged with ai.settings, which holds the models added or
   * relabelled on the AI settings form, and with the provider's own
   * configuration, where its key and endpoint live. Changing either drops the
   * entry right away instead of after OFFERED_TTL.
   *
   * @param string $cid
   *   The cache ID.
   * @param bool $offered
   *   Whether the provider offers the model.
   * @param \Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider that was asked.
   */
  protected function cacheSet(string $cid, bool $offered, ProviderProxy $provider): void {
    try {
      $tags = array_merge(['config:ai.settings'], $provider->getConfig()->getCacheTags());
      $this->cache->set($cid, $offered,
        $this->time->getRequestTime() + static::OFFERED_TTL, $tags);
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('ai_translate')->warning(
        'Could not store the model check in the cache: @message',
        ['@message' => $e->getMessage()]
      );
    }
  }

}
