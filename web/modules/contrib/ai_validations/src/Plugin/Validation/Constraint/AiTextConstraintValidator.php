<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AiText constraint.
 */
final class AiTextConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $option = $this->resolveProviderOption(
      $constraint->provider,
      'chat',
      'No AI provider specified to do validation',
    );
    if ($option === NULL) {
      return;
    }

    $userText = $data ?? '';
    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;

    if (!$this->runBooleanChat($option, $prompt, $userText)) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
