<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

/**
 * Provider request tags for AI validation chat calls.
 *
 * Shared so the validators and the AI Context consumer type stay in sync.
 * This class lives outside Plugin/AiContextConsumerType so validators can
 * tag chat() without loading the consumer type (that class depends on AI
 * Context). Context consumer IDs are a different string:
 * validation:{rule_uuid}.
 *
 * @internal
 */
final class AiValidationsRequestTags {

  /**
   * Coarse tag that makes the validation consumer type eligible.
   */
  public const ROUTING = 'ai_validations';

  /**
   * Prefix for the per-rule instance provider request tag.
   */
  public const INSTANCE_PREFIX = 'ai_validations:';

  /**
   * Builds the chat() provider request tags for a validation rule.
   *
   * @param string|null $ruleUuid
   *   The field validation rule UUID, or NULL when the constraint was not
   *   built from a rule (for example a constraint added in code).
   *
   * @return string[]
   *   Provider request tags.
   */
  public static function forRule(?string $ruleUuid): array {
    if ($ruleUuid === NULL || $ruleUuid === '') {
      return [self::ROUTING];
    }
    return [
      self::ROUTING,
      self::INSTANCE_PREFIX . $ruleUuid,
    ];
  }

}
