<?php

namespace Drupal\ai_translate;

/**
 * The chat provider and model chosen to perform a translation.
 *
 * Returned by ChatModelResolver::resolve(). It names what will actually run,
 * which is not necessarily the model selected for the translate_text
 * operation.
 *
 * @see \Drupal\ai_translate\ChatModelResolver::resolve()
 */
final class ChatModelSelection {

  /**
   * Constructs a ChatModelSelection object.
   *
   * @param string $providerId
   *   The AI provider plugin ID, for example "openai".
   * @param string $modelId
   *   The model ID, as the provider lists it in getConfiguredModels().
   */
  public function __construct(
    public readonly string $providerId,
    public readonly string $modelId,
  ) {}

  /**
   * Returns the selection in the shape the AI module uses for providers.
   *
   * That is the shape of ai.settings:default_providers.<operation>, which
   * AiProviderPluginManager::createInstance() accepts as plugin configuration.
   *
   * @return array{provider_id: string, model_id: string}
   *   The provider and model IDs.
   */
  public function toArray(): array {
    return [
      'provider_id' => $this->providerId,
      'model_id' => $this->modelId,
    ];
  }

}
