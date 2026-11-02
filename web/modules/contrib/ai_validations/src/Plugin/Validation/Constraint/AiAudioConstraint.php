<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * AI audio check constraint.
 */
#[ConstraintAttribute(
  id: 'AiAudioPrompt',
  label: new TranslatableMarkup('AI audio check', [], ['context' => 'Validation']),
)]
class AiAudioConstraint extends Constraint {

  /**
   * The prompt used to evaluate the transcript.
   *
   * @var string|null
   */
  public $prompt = NULL;

  /**
   * The message shown when the constraint is violated.
   *
   * @var string
   */
  public string $message = 'The audio content did not pass the AI validation check.';

  /**
   * The speech-to-text provider option ("provider__model").
   *
   * @var string
   */
  public $provider = '';

  /**
   * The chat provider option ("provider__model") for transcript evaluation.
   *
   * @var string
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $chat_provider = '';

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
