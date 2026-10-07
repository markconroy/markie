<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\ObjectDetection\ObjectDetectionInput;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AI object detection constraint validator.
 */
final class AiObjectDetectionConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    if (!$constraint instanceof AiObjectDetectionConstraint) {
      return;
    }

    $option = $this->resolveProviderOption(
      $constraint->model,
      'object_detection',
      'No AI model specified to do validation',
    );
    if ($option === NULL) {
      return;
    }

    // If no keywords are configured, there is nothing to validate.
    if (empty($constraint->keywords) || !is_array($constraint->keywords)) {
      return;
    }

    $file = $this->loadFile($data);
    if ($file === NULL) {
      return;
    }

    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);

    $image = new ImageFile();
    $this->applyImageStyle($image, $file, $constraint->imageStyle);
    $input = new ObjectDetectionInput($image);

    try {
      $detections = $provider->objectDetection($input, $model)->getNormalized();
    }
    catch (\Exception $e) {
      if ($constraint->na === 'fail') {
        $this->context->addViolation('AI provider failed to run object detection', []);
      }
      return;
    }

    $keywords = array_values(array_filter(array_map('strval', $constraint->keywords), 'strlen'));
    if ($keywords === []) {
      return;
    }

    $matchedKeywords = [];
    foreach ($detections as $detection) {
      $score = $detection->getConfidenceScore() ?? 0.0;
      if ($score < $constraint->minimum) {
        continue;
      }
      foreach ($keywords as $keyword) {
        if ($this->labelMatchesFinder($detection->getLabel(), $keyword, $constraint->finder)) {
          $matchedKeywords[$keyword] = TRUE;
        }
      }
    }

    $matched = $constraint->keywordFilter === 'and'
      ? count($matchedKeywords) === count($keywords)
      : !empty($matchedKeywords);

    $violation = $constraint->ruleMode === 'approve_when_found'
      ? !$matched
      : $matched;

    if ($violation) {
      $this->context->addViolation($constraint->message, []);
    }
  }

}
