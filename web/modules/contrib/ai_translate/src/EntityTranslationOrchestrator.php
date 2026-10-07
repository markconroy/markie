<?php

namespace Drupal\ai_translate;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Shared orchestration service for entity translation workflows.
 */
class EntityTranslationOrchestrator implements EntityTranslationOrchestratorInterface {

  use StringTranslationTrait;

  /**
   * Constructs the orchestrator.
   *
   * @param \Drupal\ai_translate\TextExtractorInterface $textExtractor
   *   The text extractor service.
   * @param \Drupal\ai_translate\TextTranslatorInterface $textTranslator
   *   The text translator service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected TextExtractorInterface $textExtractor,
    protected TextTranslatorInterface $textTranslator,
    protected LanguageManagerInterface $languageManager,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerChannelFactoryInterface $loggerFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function checkTranslateAccess(
    ContentEntityInterface $entity,
    AccountInterface $account,
    string $langTo,
  ): AccessResultInterface {
    $access = AccessResult::allowedIfHasPermission($account, 'create ai content translation');
    if (!$access->isAllowed()) {
      return $access;
    }

    if (!$entity->isTranslatable()) {
      return $access->andIf(AccessResult::forbidden('The entity is not translatable.'));
    }

    try {
      $translationHandler = $this->entityTypeManager
        ->getHandler($entity->getEntityTypeId(), 'translation');
    }
    catch (InvalidPluginDefinitionException) {
      return $access->andIf(AccessResult::forbidden('The entity type has no translation handler.'));
    }

    return $access
      ->andIf($entity->access('update', $account, TRUE))
      ->andIf($translationHandler->getTranslationAccess($entity, 'create'));
  }

  /**
   * {@inheritdoc}
   */
  public function resolveSourceEntity(ContentEntityInterface $entity, string $langFrom): ContentEntityInterface {
    if ($entity->language()->getId() !== $langFrom && $entity->hasTranslation($langFrom)) {
      return $entity->getTranslation($langFrom);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function extractTextMetadata(ContentEntityInterface $entity): array {
    return $this->textExtractor->extractTextMetadata($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function translateTextMetadataItem(array $singleField, LanguageInterface $langFrom, LanguageInterface $langTo): ?array {
    $translatedText = [];
    foreach ($singleField['_columns'] as $column) {
      try {
        $translatedText[$column] = '';
        if (!empty($singleField[$column])) {
          $translatedText[$column] = $this->textTranslator->translateContent(
            $singleField[$column],
            $langTo,
            $langFrom,
          );
        }
      }
      catch (TranslationException) {
        return NULL;
      }
    }

    // Decodes HTML entities in translation.
    // Because of sanitation in StringFormatter/Markup, this should be safe.
    foreach ($translatedText as &$translatedTextItem) {
      $translatedTextItem = html_entity_decode($translatedTextItem);
    }
    unset($translatedTextItem);

    $singleField['translated'] = $translatedText;
    return $singleField;
  }

  /**
   * {@inheritdoc}
   */
  public function saveTranslatedEntity(
    ContentEntityInterface $entity,
    string $langTo,
    array $processedTranslations,
    array $failures = [],
  ): EntityTranslationResult {
    $translation = $entity->addTranslation($langTo, $entity->toArray());
    $this->applyTranslationStatus($entity, $translation);
    $this->textExtractor->insertTextMetadata($translation, $processedTranslations);

    try {
      $translation->save();
      return EntityTranslationResult::success(
        $translation,
        $this->t('Content translated successfully.'),
        $failures,
      );
    }
    catch (\Throwable $exception) {
      $this->loggerFactory->get('ai_translate')->warning('Entity translation save failed: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return EntityTranslationResult::failure(
        $this->t('There was some issue with content translation.'),
        $failures,
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function translateEntity(ContentEntityInterface $entity, string $langFrom, string $langTo): EntityTranslationResult {
    $entity = $this->resolveSourceEntity($entity, $langFrom);
    if ($entity->hasTranslation($langTo)) {
      return EntityTranslationResult::existing($this->t('Translation already exists.'));
    }

    // The target language must exist, as it decides which translation gets
    // created. The source language is only used to tell the LLM what it is
    // translating from, so an unknown code falls back to the language of the
    // (already resolved) source entity rather than failing the whole request.
    $langToObject = $this->languageManager->getLanguage($langTo);
    if (!$langToObject instanceof LanguageInterface) {
      return EntityTranslationResult::failure($this->t('Invalid target language.'));
    }
    $langFromObject = $this->languageManager->getLanguage($langFrom) ?? $entity->language();

    $processedTranslations = [];
    $failures = [];
    foreach ($this->extractTextMetadata($entity) as $singleField) {
      $translatedField = $this->translateTextMetadataItem($singleField, $langFromObject, $langToObject);
      if ($translatedField === NULL) {
        $failures[] = $singleField['field_name'] ?? (string) reset($singleField['parents']);
        continue;
      }
      $processedTranslations[] = $translatedField;
    }

    return $this->saveTranslatedEntity($entity, $langTo, $processedTranslations, $failures);
  }

  /**
   * Applies configured publication/moderation behavior to a translation.
   */
  protected function applyTranslationStatus(ContentEntityInterface $entity, ContentEntityInterface $translation): void {
    if (!$entity instanceof EntityPublishedInterface) {
      return;
    }

    $translationStatus = $this->configFactory->get('ai_translate.settings')->get('translation_status') ?? 'keep_original';
    if ('create_draft' !== $translationStatus) {
      return;
    }

    if ($entity->getEntityType()->isRevisionable()) {
      $translation->setRevisionTranslationAffected(NULL);
    }
    if ($entity->hasField('moderation_state')) {
      $translation->set('moderation_state', 'draft');
    }
    if ($translation instanceof EntityPublishedInterface) {
      $translation->setUnpublished();
    }
  }

}
