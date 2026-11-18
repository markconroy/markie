<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

/**
 * Provider request tags for AI Translate chat-proxy calls.
 *
 * Shared so ChatTranslationProvider and the consumer type stay in
 * sync. This class lives outside Plugin/AiContextConsumerType so the
 * provider can tag chat() without loading the consumer type (that
 * class depends on AI Context). Context consumer IDs are a different
 * string: translate:default.
 *
 * @internal
 */
final class AiTranslateRequestTags {

  /**
   * Coarse tag that makes the Translate consumer type eligible.
   *
   * In 1.0 this tag also claims the single translate:default instance.
   */
  public const ROUTING = 'ai_translate';

  /**
   * Builds the chat() provider request tags.
   *
   * One instance is enough in 1.0, so the coarse module tag also
   * identifies the consumer. Language matching uses
   * language_context metadata, not extra instance tags.
   *
   * @return string[]
   *   Provider request tags.
   */
  public static function forChat(): array {
    return [self::ROUTING];
  }

}
