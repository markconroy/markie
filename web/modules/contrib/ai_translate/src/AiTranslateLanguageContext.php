<?php

declare(strict_types=1);

namespace Drupal\ai_translate;

use Drupal\Core\Config\ConfigBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Builds language_context for chat-proxy translation.
 *
 * Source and target langcodes are copied onto the ChatInput so a
 * later CCC Language-scope change can match French-only vs
 * language-neutral items. This module still uses one consumer
 * (translate:default). The route is never used.
 *
 * @internal
 */
final class AiTranslateLanguageContext {

  /**
   * Request-metadata key.
   */
  public const KEY = 'language_context';

  /**
   * Config name prefix of a configurable language.
   *
   * The language add form limits custom langcodes to 12 characters and the
   * core language code pattern, but languages created through the API or a
   * config import are only limited by the config name length. Every langcode
   * that can exist on a site must pass, or language_context is lost for it.
   */
  private const LANGUAGE_CONFIG_PREFIX = 'language.entity.';

  /**
   * Builds sanitized language_context from TranslateTextInput languages.
   *
   * @param string|null $source
   *   Source langcode, or NULL when unknown.
   * @param string $target
   *   Target langcode.
   *
   * @return array<string, string>|null
   *   Keys target and optional source, or NULL when target is invalid.
   */
  public static function fromLanguages(?string $source, string $target): ?array {
    return self::sanitize([
      'source' => $source,
      'target' => $target,
    ]);
  }

  /**
   * Reads a sanitized language_context from an operation input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   TranslateTextInput or ChatInput.
   *
   * @return array<string, string>|null
   *   Sanitized language_context, or NULL when missing or invalid.
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
   * Writes language_context onto an operation input.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   TranslateTextInput or ChatInput.
   * @param array<string, string> $languageContext
   *   Sanitized language_context.
   */
  public static function applyToInput(
    InputInterface $input,
    array $languageContext,
  ): void {
    if (!self::inputSupportsRequestMetadata($input)) {
      return;
    }
    $input->setRequestMetadataValue(self::KEY, $languageContext);
  }

  /**
   * Whether this input can store request metadata.
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
   * Keeps only scalar source and target langcodes.
   *
   * Target is required. Source is omitted when missing or invalid.
   * Extra keys are dropped.
   *
   * @param array<int|string, mixed> $languageContext
   *   Raw language_context from input metadata or language getters.
   *
   * @return array<string, string>|null
   *   Sanitized values, or NULL when target is missing.
   */
  public static function sanitize(array $languageContext): ?array {
    $target = self::sanitizeLangcode($languageContext['target'] ?? NULL);
    if ($target === NULL) {
      return NULL;
    }

    $sanitized = ['target' => $target];
    $source = self::sanitizeLangcode($languageContext['source'] ?? NULL);
    if ($source !== NULL) {
      $sanitized['source'] = $source;
    }

    return $sanitized;
  }

  /**
   * Normalizes a langcode for request metadata.
   *
   * @param mixed $value
   *   Raw langcode.
   *
   * @return string|null
   *   A safe langcode, or NULL when unusable.
   */
  private static function sanitizeLangcode(mixed $value): ?string {
    if (!is_string($value)) {
      return NULL;
    }
    $code = trim($value);
    $maxLength = ConfigBase::MAX_NAME_LENGTH - strlen(self::LANGUAGE_CONFIG_PREFIX);
    if ($code === '' || strlen($code) > $maxLength) {
      return NULL;
    }
    if (preg_match('/^[a-zA-Z0-9_\-]+$/', $code) !== 1) {
      return NULL;
    }
    return $code;
  }

}
