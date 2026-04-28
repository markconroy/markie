<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProviderModelDependency constraint.
 */
class ProviderModelDependencyConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$constraint instanceof ProviderModelDependencyConstraint) {
      return;
    }

    // If value is null or not an array, nothing to validate.
    if (!is_array($value)) {
      return;
    }

    // Check if model_id is set but provider_id is not.
    if (!empty($value['model_id']) && empty($value['provider_id'])) {
      $this->context->addViolation($constraint->message);
    }

    // Check the reverse: if provider_id is set but model_id is not.
    if (!empty($value['provider_id']) && empty($value['model_id'])) {
      $this->context->addViolation($constraint->message);
    }
  }

}
