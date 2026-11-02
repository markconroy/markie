<?php

declare(strict_types=1);

namespace Drupal\ai_validations;

use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Builds entity_context for validation chat calls.
 *
 * AI Context never reads the route. Entity Types, Taxonomy, and
 * Specific Entities match only when this metadata is on the ChatInput.
 * When the validator has no content-entity host, the metadata is left
 * unset. Always include and Global still work without it.
 *
 * @internal
 */
final class AiValidationsEntityContext {

  /**
   * Request-metadata key consumed by AI Context.
   */
  public const KEY = 'entity_context';

  /**
   * Builds entity_context from a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being validated.
   *
   * @return array<string, string>|null
   *   Keys entity_type, bundle, and id; id is empty for unsaved
   *   entities. NULL when the entity type is empty.
   *
   * @internal
   */
  public static function fromEntity(
    ContentEntityInterface $entity,
  ): ?array {
    $entity_type = $entity->getEntityTypeId();
    if ($entity_type === '') {
      return NULL;
    }
    $id = $entity->id();
    return [
      'entity_type' => $entity_type,
      'bundle' => $entity->bundle(),
      'id' => ($id === NULL || $id === '') ? '' : (string) $id,
    ];
  }

  /**
   * Builds entity_context from a Symfony validator execution context.
   *
   * Prefers the current object, then the validation root. The object is
   * the field being validated, so its host entity is exact even when a
   * nested entity is validated inside a parent entity's context. Unwraps
   * field items and typed-data adapters. Does not read the route or
   * invent a path.
   *
   * @param \Symfony\Component\Validator\Context\ExecutionContextInterface $context
   *   The validator execution context.
   *
   * @return array<string, string>|null
   *   Sanitized entity_context, or NULL when no content entity is
   *   available.
   *
   * @internal
   */
  public static function fromValidatorContext(
    ExecutionContextInterface $context,
  ): ?array {
    foreach ([$context->getObject(), $context->getRoot()] as $value) {
      $entity = self::contentEntityFromValue($value);
      if ($entity === NULL) {
        continue;
      }
      $entity_context = self::fromEntity($entity);
      if ($entity_context !== NULL) {
        return $entity_context;
      }
    }
    return NULL;
  }

  /**
   * Writes entity_context onto an operation input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   Usually a ChatInput.
   * @param array<string, string> $entityContext
   *   Sanitized entity_context.
   *
   * @internal
   */
  public static function applyToInput(
    InputInterface $input,
    array $entityContext,
  ): void {
    if (!self::inputSupportsRequestMetadata($input)) {
      return;
    }
    $input->setRequestMetadataValue(self::KEY, $entityContext);
  }

  /**
   * Whether this input can store request metadata.
   *
   * AI 1.2-1.4 and some 2.x snapshots do not implement
   * get/setRequestMetadataValue(). Entity-scoped CCC matching is a
   * no-op on those versions.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   Usually a ChatInput.
   *
   * @return bool
   *   TRUE when both get and set methods exist.
   *
   * @internal
   */
  public static function inputSupportsRequestMetadata(
    InputInterface $input,
  ): bool {
    return method_exists($input, 'getRequestMetadataValue')
      && method_exists($input, 'setRequestMetadataValue');
  }

  /**
   * Unwraps a content entity from a validator value.
   *
   * @param mixed $value
   *   A validation root or current object.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity, or NULL.
   */
  private static function contentEntityFromValue(mixed $value): ?ContentEntityInterface {
    if ($value instanceof ContentEntityInterface) {
      return $value;
    }
    if ($value instanceof FieldItemListInterface
      || $value instanceof FieldItemInterface) {
      $entity = $value->getEntity();
      return $entity instanceof ContentEntityInterface ? $entity : NULL;
    }
    if ($value instanceof TypedDataInterface) {
      $inner = $value->getValue();
      return $inner instanceof ContentEntityInterface ? $inner : NULL;
    }
    return NULL;
  }

}
