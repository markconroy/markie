<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AiImage constraint.
 */
final class AiImageConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $option = $this->resolveProviderOption(
      $constraint->provider,
      'chat_with_image_vision',
      'No AI provider specified to do validation',
    );
    if ($option === NULL) {
      return;
    }

    $file = $this->loadFile($data);
    if ($file === NULL) {
      return;
    }

    $image = new ImageFile();
    $this->applyImageStyle($image, $file, $constraint->imageStyle);

    if ($constraint->warningMessage !== '') {
      // Ternary XSAFE/XWARN/XBLOCK protocol. Only the prompt is sent: the
      // user-facing messages are not appended, so they cannot pollute the
      // anchored token parsing.
      $verdict = $this->runTernaryChat($option, (string) $constraint->prompt, (string) $data, $image, ruleUuid: $constraint->ruleUuid);
      if ($verdict === NULL) {
        // Validation could not run; the failure has already been logged and
        // a distinct violation already raised by runTernaryChat().
        return;
      }
      if ($verdict === self::VERDICT_BLOCK) {
        $this->context->addViolation($constraint->message, []);
      }
      elseif ($verdict === self::VERDICT_WARN) {
        $this->emitWarning($constraint->warningMessage);
      }
      return;
    }

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;
    $result = $this->runBooleanChat($option, $prompt, (string) $data, $image, ruleUuid: $constraint->ruleUuid);
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
