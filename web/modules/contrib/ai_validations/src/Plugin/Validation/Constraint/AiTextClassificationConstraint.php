<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ai text classification check constraint.
 *
 * @Constraint(
 *   id = "AiTextClassification",
 *   label = @Translation("AI Text Classification Check", context = "Validation"),
 * )
 */
class AiTextClassificationConstraint extends Constraint {

  /**
   * The message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public $message = '';

  /**
   * The AI model.
   *
   * @var string
   */
  public $model = '';

  /**
   * The classification tag.
   *
   * @var string
   */
  public $tag = '';

  /**
   * The type of finder.
   *
   * @var string
   */
  public $finder = '';

  /**
   * The minimum confidence to pass.
   *
   * @var float
   */
  public $minimum = 0.0;

  /**
   * The model is not available.
   *
   * @var string|null
   */
  public $na;

}
