<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\ai\OperationType\InputInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Builds and copies entity_context for chat-proxy translation.
 *
 * The generic CCC path never reads the route. Entity Types, Taxonomy,
 * and Specific Entities match only when this metadata is on the
 * ChatInput. Interface translation and ai:translate-text have no
 * entity and leave it unset.
 *
 * @internal
 */
final class AiTranslateEntityContext {

  /**
   * Request-metadata and translateContent() context key.
   */
  public const KEY = 'entity_context';

  /**
   * Builds a translateContent() $context array holding entity_context.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity, already resolved to the source language.
   *
   * @return array<string, array<string, string>>
   *   A translateContent() $context array with only entity_context, or
   *   empty when the entity has no usable type.
   */
  public static function toContext(
    ContentEntityInterface $entity,
  ): array {
    $entity_context = self::fromEntity($entity);
    if ($entity_context === NULL) {
      return [];
    }
    return [self::KEY => $entity_context];
  }

  /**
   * Builds entity_context from a loaded content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being translated.
   *
   * @return array<string, string>|null
   *   Keys entity_type, bundle, and id; id is empty for unsaved
   *   entities. NULL when the entity type is empty.
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
   * Reads a sanitized entity_context from a translateContent() array.
   *
   * @param array<string, mixed> $context
   *   The $context argument from translateContent(); other keys are ignored.
   *
   * @return array<string, string>|null
   *   Sanitized entity_context, or NULL when missing or invalid.
   */
  public static function fromContext(array $context): ?array {
    if (!isset($context[self::KEY]) || !is_array($context[self::KEY])) {
      return NULL;
    }
    return self::sanitize($context[self::KEY]);
  }

  /**
   * Whether this input can store request metadata.
   *
   * AI 1.2-1.4 and some 2.x snapshots do not implement
   * get/setRequestMetadataValue(). Entity-scoped CCC matching is a
   * no-op on those versions.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   TranslateTextInput or ChatInput.
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
   * Reads a sanitized entity_context from an operation input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   TranslateTextInput or ChatInput.
   *
   * @return array<string, string>|null
   *   Sanitized entity_context, or NULL when missing or invalid.
   */
  public static function fromInput(InputInterface $input): ?array {
    if (!self::inputSupportsRequestMetadata($input)) {
      return NULL;
    }
    $value = $input->getRequestMetadataValue(self::KEY);
    if (!is_array($value)) {
      return NULL;
    }
    return self::sanitize($value);
  }

  /**
   * Copies entity_context from a translateContent() array onto an input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   Usually a TranslateTextInput.
   * @param array<string, mixed> $context
   *   The $context argument from translateContent(); other keys are ignored.
   */
  public static function applyFromContext(
    InputInterface $input,
    array $context,
  ): void {
    $entity_context = self::fromContext($context);
    if ($entity_context === NULL) {
      return;
    }
    self::applyToInput($input, $entity_context);
  }

  /**
   * Writes entity_context onto an operation input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   TranslateTextInput or ChatInput.
   * @param array<string, string> $entityContext
   *   Sanitized entity_context.
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
   * Keeps only scalar entity identity keys.
   *
   * Does not read the route or invent a path. Callers that have no
   * entity omit this metadata entirely.
   *
   * @param array<int|string, mixed> $entityContext
   *   Raw entity_context from a $context array or input metadata.
   *
   * @return array<string, string>|null
   *   Sanitized values, or NULL when entity_type is missing.
   */
  public static function sanitize(array $entityContext): ?array {
    $type = $entityContext['entity_type'] ?? $entityContext['type'] ?? NULL;
    if (!is_string($type) || $type === '') {
      return NULL;
    }

    $sanitized = ['entity_type' => $type];
    $bundle = $entityContext['bundle'] ?? NULL;
    if (is_string($bundle) && $bundle !== '') {
      $sanitized['bundle'] = $bundle;
    }

    if (!array_key_exists('id', $entityContext)) {
      return $sanitized;
    }
    $id = $entityContext['id'];
    if ($id === NULL || $id === '') {
      $sanitized['id'] = '';
      return $sanitized;
    }
    if (is_int($id) || is_string($id)) {
      $sanitized['id'] = (string) $id;
    }

    return $sanitized;
  }

}
