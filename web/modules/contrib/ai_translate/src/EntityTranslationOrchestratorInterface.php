<?php

namespace Drupal\ai_translate;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for the shared entity translation orchestration service.
 */
interface EntityTranslationOrchestratorInterface {

  /**
   * Checks whether an account may AI-translate an entity into a language.
   *
   * This is the single gate shared by every request-context entry point.
   * Callers must invoke it before translating on behalf of a user.
   * Method translateEntity() deliberately does not, so that trusted contexts
   * such as Drush can translate without an access-bearing account.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to translate.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check.
   * @param string $langTo
   *   The target language code. Note that an already existing translation is
   *   not an access failure: callers that must reject it (such as the translate
   *   route) check for it themselves, so that programmatic callers can still
   *   report it as a skipped translation rather than as denied access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkTranslateAccess(
    ContentEntityInterface $entity,
    AccountInterface $account,
    string $langTo,
  ): AccessResultInterface;

  /**
   * Resolves the source translation for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to translate.
   * @param string $langFrom
   *   The source language code.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity translation in the source language, or the entity itself if
   *   it has no translation in that language.
   */
  public function resolveSourceEntity(ContentEntityInterface $entity, string $langFrom): ContentEntityInterface;

  /**
   * Extracts text metadata from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract translatable text from.
   *
   * @return array
   *   A flat list of text metadata items, one per field delta. See
   *   \Drupal\ai_translate\TextExtractorInterface::extractTextMetadata().
   */
  public function extractTextMetadata(ContentEntityInterface $entity): array;

  /**
   * Translates a single extracted metadata item.
   *
   * @param array $singleField
   *   A single text metadata item, as returned by extractTextMetadata().
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   The target language.
   *
   * @return array|null
   *   The metadata item with a 'translated' key added, keyed by column, or
   *   NULL if the item could not be translated.
   */
  public function translateTextMetadataItem(array $singleField, LanguageInterface $langFrom, LanguageInterface $langTo): ?array;

  /**
   * Creates and saves a translated entity from translated metadata.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity to add the translation to.
   * @param string $langTo
   *   The target language code.
   * @param array $processedTranslations
   *   The successfully translated text metadata items.
   * @param string[] $failures
   *   The names of the fields that could not be translated, passed through to
   *   the result so callers can report partial failures.
   *
   * @return \Drupal\ai_translate\EntityTranslationResult
   *   The translation result.
   */
  public function saveTranslatedEntity(
    ContentEntityInterface $entity,
    string $langTo,
    array $processedTranslations,
    array $failures = [],
  ): EntityTranslationResult;

  /**
   * Translates an entity end-to-end.
   *
   * Fields that fail to translate are skipped and reported in the result, so a
   * partially translated entity is still saved.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to translate.
   * @param string $langFrom
   *   The source language code.
   * @param string $langTo
   *   The target language code.
   *
   * @return \Drupal\ai_translate\EntityTranslationResult
   *   The translation result.
   */
  public function translateEntity(ContentEntityInterface $entity, string $langFrom, string $langTo): EntityTranslationResult;

}
