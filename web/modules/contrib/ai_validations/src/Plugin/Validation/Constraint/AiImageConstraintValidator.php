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

    $prompt = $constraint->prompt . PHP_EOL . $constraint->message;
    if (!$this->runBooleanChat($option, $prompt, (string) $data, $image)) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
