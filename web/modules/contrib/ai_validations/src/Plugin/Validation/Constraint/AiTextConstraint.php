<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Ai check constraint.
 */
#[ConstraintAttribute(
  id: 'AiTextPrompt',
  label: new TranslatableMarkup('AI check', [], ['context' => 'Validation']),
)]
class AiTextConstraint extends Constraint {

  /**
   * Prompt.
   *
   * @var string|null
   */
  public $prompt = NULL;

  /**
   * Message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public string $message = 'The text content did not pass the AI validation check.';

  /**
   * Provider.
   *
   * @var string
   */
  public $provider = '';

  /**
   * The UUID of the field validation rule that built this constraint.
   *
   * Used to tag the chat request so AI Context can push context for that
   * rule. NULL when the constraint was not built from a rule.
   *
   * @var string|null
   */
  public ?string $ruleUuid = NULL;

}
