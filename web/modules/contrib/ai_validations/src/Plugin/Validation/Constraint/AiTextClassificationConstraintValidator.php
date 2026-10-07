<?php

declare(strict_types=1);

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\ai\OperationType\TextClassification\TextClassificationInput;
use Drupal\ai_validations\AiConstraintValidatorBase;
use Symfony\Component\Validator\Constraint;

/**
 * AiText classification constraint.
 */
final class AiTextClassificationConstraintValidator extends AiConstraintValidatorBase {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $data, Constraint $constraint): void {
    $option = $this->resolveProviderOption(
      $constraint->model,
      'text_classification',
      'No AI model specified to do validation',
      $constraint->na !== 'skip',
    );
    if ($option === NULL) {
      return;
    }

    $text = (string) ($data ?? '');
    if ($text === '') {
      return;
    }

    $input = new TextClassificationInput($text);

    $provider = $this->aiPluginManager->loadProviderFromSimpleOption($option);
    $model = $this->aiPluginManager->getModelNameFromSimpleOption($option);

    try {
      $classifications = $provider->textClassification($input, $model)->getNormalized();
    }
    catch (\Exception $e) {
      if ($constraint->na === 'fail') {
        $this->context->addViolation('AI provider failed to classify text', []);
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
