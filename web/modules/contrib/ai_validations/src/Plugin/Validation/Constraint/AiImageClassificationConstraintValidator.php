<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ImageClassification\ImageClassificationInput;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AiImage classification constraint.
 */
final class AiImageClassificationConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $option = $this->resolveProviderOption(
      $constraint->model,
      'image_classification',
      'No AI model specified to do validation',
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
    $input = new ImageClassificationInput($image);

    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);

    try {
      $classifications = $provider->imageClassification($input, $model)->getNormalized();
    }
    catch (\Exception $e) {
      if ($constraint->na === 'fail') {
        $this->context->addViolation('AI provider failed to classify image', []);
      }
      return;
    }

    foreach ($classifications as $classification) {
      $matchesTag = $this->labelMatchesFinder(
        $classification->getLabel(),
        $constraint->tag,
        $constraint->finder,
      );
      if ($matchesTag && $classification->getConfidenceScore() >= $constraint->minimum) {
        $this->context->addViolation($constraint->message, []);
      }
    }
  }

}
