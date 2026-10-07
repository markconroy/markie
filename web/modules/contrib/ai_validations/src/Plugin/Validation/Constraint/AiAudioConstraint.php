<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * AI audio check constraint.
 *
 * @Constraint(
 *   id = "AiAudioPrompt",
 *   label = @Translation("AI audio check", context = "Validation"),
 * )
 */
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
  public $message = '';

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

}
