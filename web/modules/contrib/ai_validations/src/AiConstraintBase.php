<?php

namespace Drupal\ai_validations;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;

/**
 * Base class for the AI validation constraints.
 *
 * Drupal 11.4 creates constraints with named arguments when the constructor
 * has the HasNamedArguments attribute, and Drupal 12 always does. Older
 * versions pass all options as an array in $options, which still works.
 *
 * This file does not declare strict types, so option values are converted to
 * the property types the same way Symfony's own option handling does.
 */
abstract class AiConstraintBase extends Constraint {

  /**
   * Constructs the constraint.
   *
   * @param mixed $options
   *   The options as an array, as passed by Drupal versions before 11.4.
   * @param array|null $groups
   *   The validation groups.
   * @param mixed $payload
   *   Domain-specific data attached to the constraint.
   * @param mixed ...$properties
   *   The constraint options as named arguments, keyed by property name.
   */
  #[HasNamedArguments]
  public function __construct(mixed $options = NULL, ?array $groups = NULL, mixed $payload = NULL, mixed ...$properties) {
    if (is_array($options)) {
      $groups ??= $options['groups'] ?? NULL;
      $payload ??= $options['payload'] ?? NULL;
      unset($options['groups'], $options['payload']);
      $properties += $options;
    }

    $invalid = [];
    foreach ($properties as $name => $value) {
      if (!is_string($name) || !property_exists($this, $name)) {
        $invalid[] = $name;
        continue;
      }
      $this->{$name} = $value;
    }
    if ($invalid) {
      throw new InvalidOptionsException(sprintf('The options "%s" do not exist in constraint "%s".', implode('", "', $invalid), static::class), $invalid);
    }

    parent::__construct(NULL, $groups, $payload);
  }

}
