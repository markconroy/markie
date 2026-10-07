<?php

namespace Drupal\ai_translate;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Value object for entity translation results.
 */
class EntityTranslationResult {

  /**
   * Constructs a translation result.
   *
   * @param bool $success
   *   Whether the translation succeeded.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $translatedEntity
   *   The translated entity, if one was created.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The user-facing result message.
   * @param bool $translationExists
   *   Whether a translation already existed for the target language.
   * @param string[] $failures
   *   The names of the fields that could not be translated.
   */
  public function __construct(
    protected bool $success,
    protected ?ContentEntityInterface $translatedEntity = NULL,
    protected string|TranslatableMarkup $message = '',
    protected bool $translationExists = FALSE,
    protected array $failures = [],
  ) {}

  /**
   * Creates a successful result.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $translatedEntity
   *   The translated entity.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The user-facing result message.
   * @param string[] $failures
   *   The names of the fields that could not be translated.
   *
   * @return self
   *   The translation result.
   */
  public static function success(
    ContentEntityInterface $translatedEntity,
    string|TranslatableMarkup $message,
    array $failures = [],
  ): self {
    return new self(TRUE, $translatedEntity, $message, FALSE, $failures);
  }

  /**
   * Creates an existing-translation result.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The user-facing result message.
   *
   * @return self
   *   The translation result.
   */
  public static function existing(string|TranslatableMarkup $message): self {
    return new self(FALSE, NULL, $message, TRUE);
  }

  /**
   * Creates a failure result.
   *
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The user-facing result message.
   * @param string[] $failures
   *   The names of the fields that could not be translated.
   *
   * @return self
   *   The translation result.
   */
  public static function failure(string|TranslatableMarkup $message, array $failures = []): self {
    return new self(FALSE, NULL, $message, FALSE, $failures);
  }

  /**
   * Indicates if the translation succeeded.
   *
   * @return bool
   *   TRUE if a translation was created and saved.
   */
  public function isSuccess(): bool {
    return $this->success;
  }

  /**
   * Indicates if the translation already existed.
   *
   * @return bool
   *   TRUE if the entity already had a translation in the target language.
   */
  public function translationExists(): bool {
    return $this->translationExists;
  }

  /**
   * Gets the translated entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The translated entity, or NULL if no translation was created.
   */
  public function getTranslatedEntity(): ?ContentEntityInterface {
    return $this->translatedEntity;
  }

  /**
   * Gets the result message.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The user-facing result message. Cast to string when a plain string is
   *   required, for example for machine-readable output.
   */
  public function getMessage(): string|TranslatableMarkup {
    return $this->message;
  }

  /**
   * Gets any field-level failures.
   *
   * @return string[]
   *   The names of the fields that could not be translated.
   */
  public function getFailures(): array {
    return $this->failures;
  }

}
