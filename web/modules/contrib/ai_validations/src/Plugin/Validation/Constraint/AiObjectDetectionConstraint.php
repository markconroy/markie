<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * AI object detection check constraint.
 *
 * @Constraint(
 *   id = "AiObjectDetection",
 *   label = @Translation("AI Object Detection Check", context = "Validation"),
 * )
 */
class AiObjectDetectionConstraint extends Constraint {

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
   * The object detection keywords.
   *
   * @var string[]
   */
  public $keywords = [];

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
   * How to combine multiple keywords.
   *
   * Allowed values: and, or.
   *
   * @var string
   */
  public $keywordFilter = 'or';

  /**
   * The rule mode for matching keywords.
   *
   * Allowed values: disapprove_when_found, approve_when_found.
   *
   * @var string
   */
  public $ruleMode = 'disapprove_when_found';

  /**
   * Behavior when the model is not available.
   *
   * Allowed values: skip, fail.
   *
   * @var string|null
   */
  public $na;

  /**
   * The image style ID to apply before sending to the provider.
   *
   * @var string|null
   */
  public $imageStyle = NULL;

}
