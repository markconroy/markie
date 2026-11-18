<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

/**
 * Shared CCC identifiers, usable without loading an optional consumer plugin.
 *
 * @internal
 */
final class AiTranslateContext {

  /**
   * Canonical ID of the chat-proxy consumer.
   */
  public const CONSUMER_ID = 'translate:default';

  /**
   * Configuration object for the Translate consumer type.
   */
  public const TYPE_SETTINGS_CONFIG = 'ai_context.consumer_type_settings.translate';

  /**
   * Setting that allows cross-entity translation cache reuse while pushing.
   */
  public const SHARE_ENTITY_CACHE = 'share_entity_cache';

}
