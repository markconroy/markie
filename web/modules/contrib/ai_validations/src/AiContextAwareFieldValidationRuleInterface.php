<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

/**
 * Marks a field validation rule whose constraint runs a chat prompt.
 *
 * Rules that implement this pass their UUID to the constraint as the
 * "ruleUuid" option, so the chat request is tagged per rule and AI Context
 * can push context into its system prompt. Each rule is listed as a
 * validation:{rule_uuid} consumer. The constraint must declare a public
 * $ruleUuid property.
 *
 * This interface does not depend on AI Context. Without it, the tags are
 * simply ignored.
 */
interface AiContextAwareFieldValidationRuleInterface {

}
