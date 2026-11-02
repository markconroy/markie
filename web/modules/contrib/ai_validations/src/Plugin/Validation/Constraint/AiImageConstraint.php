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
  id: 'AiImagePrompt',
  label: new TranslatableMarkup('AI check', [], ['context' => 'Validation']),
)]
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
  public string $message = 'The image content did not pass the AI validation check.';

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

  /**
   * Optional warning shown when the model answers XWARN.
   *
   * When non-empty, the validator switches from the boolean XTRUE/XFALSE
   * protocol to the ternary XSAFE/XWARN/XBLOCK protocol: an XWARN verdict
   * emits this message as a non-blocking warning and the content is
   * accepted. When empty (the default), the legacy boolean protocol is used
   * unchanged.
   *
   * @var string
   */
  public string $warningMessage = '';

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
