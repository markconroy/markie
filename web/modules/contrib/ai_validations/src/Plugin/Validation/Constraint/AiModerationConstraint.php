<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * AI moderation constraint.
 */
#[ConstraintAttribute(
  id: 'AiModeration',
  label: new TranslatableMarkup('AI Moderation Check', [], ['context' => 'Validation']),
)]
class AiModerationConstraint extends Constraint {

  /**
   * Comma-separated list of moderation categories to watch.
   *
   * Empty means any flagged category triggers a violation.
   *
   * @var string
   */
  public string $categories = '';

  /**
   * Message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public string $message = 'The content did not pass the AI moderation check.';

  /**
   * Provider/model string in "provider__model" format.
   *
   * @var string
   */
  public string $provider = '';

  /**
   * Minimum confidence score (0–1) for a category to trigger a violation.
   *
   * Only applied when specific categories are configured.
   *
   * @var float
   */
  public float $threshold = 0.5;

}
