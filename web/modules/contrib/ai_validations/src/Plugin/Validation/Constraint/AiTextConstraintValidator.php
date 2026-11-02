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

    $userText = (string) ($data ?? '');
    if ($userText === '') {
      return;
    }

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;

    $result = $this->runBooleanChat($option, $prompt, $userText, ruleUuid: $constraint->ruleUuid);
    if ($result === NULL) {
      // Validation could not run; the failure has already been logged and a
      // distinct violation already raised by runBooleanChat().
      return;
    }
    if (!$result) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
