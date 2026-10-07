<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ai check constraint.
 *
 * @Constraint(
 *   id = "AiImagePrompt",
 *   label = @Translation("AI check", context = "Validation"),
 * )
 */
class AiImageConstraint extends Constraint {

  /**
   * The prompt.
   *
   * @var string|null
   */
  public $prompt = NULL;

  /**
   * The message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public $message = '';

  /**
   * The provider.
   *
   * @var string
   */
  public $provider = '';

  /**
   * The image style ID to apply before sending to the provider.
   *
   * @var string|null
   */
  public $imageStyle = NULL;

}
