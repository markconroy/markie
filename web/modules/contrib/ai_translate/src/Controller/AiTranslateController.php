<?php

namespace Drupal\ai_translate\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai_translate\EntityTranslationOrchestratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Defines an AI Translate Controller.
 */
class AiTranslateController extends ControllerBase {

  use DependencySerializationTrait;

  /**
   * Shared entity translation orchestrator.
   *
   * @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface
   */
  protected EntityTranslationOrchestratorInterface $translationOrchestrator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->languageManager = $container->get('language_manager');
    $instance->translationOrchestrator = $container->get('ai_translate.translation_orchestrator');
    return $instance;
  }

  /**
   * Add requested entity translation to the original content.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_from
   *   Source language code.
   * @param string $lang_to
   *   Target language code.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   the function will return a RedirectResponse to the translation
   *   overview page by showing a success or error message.
   */
  public function translate(string $entity_type, string $entity_id, string $lang_from, string $lang_to) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id);

    $entity = $this->translationOrchestrator->resolveSourceEntity($entity, $lang_from);

    $redirectUrl = ('edit' === $this->config('ai_translate.settings')
      ->get('redirect_after_create'))
      ? $entity->toUrl('edit-form', ['language' => $langNames[$lang_to]])
      : $entity->toUrl('drupal:content-translation-overview');
    $response = new RedirectResponse($redirectUrl->setAbsolute()->toString());

    // @todo support updating existing translations.
    if ($entity->hasTranslation($lang_to)) {
      $this->messenger()->addMessage($this->t('Translation already exists.'));
      $response->send();
      return $response;
    }

    $textMetadata = $this->translationOrchestrator->extractTextMetadata($entity);

    // Creates a batch builder to translate text metadata.
    $batchBuilder = (new BatchBuilder())
      ->setTitle($this->t('Translating entity content with AI'))
      ->setInitMessage($this->t('Batch is starting'))
      ->setErrorMessage($this->t('Batch has encountered an error'));

    foreach ($textMetadata as $singleMeta) {
      $batchBuilder->addOperation([$this, 'translateSingleField'], [
        $singleMeta,
        $langNames[$lang_from],
        $langNames[$lang_to],
      ]);
    }
    $batchBuilder->addOperation([$this, 'insertTranslation'], [$entity, $lang_to]);
    batch_set($batchBuilder->toArray());
    return batch_process($redirectUrl);
  }

  /**
   * Finished operation.
   */
  public static function finish($success, $results, $operations, $duration) {
    $messenger = \Drupal::messenger();
    if ($success) {
      $messenger->addMessage(t('All terms have been processed.'));
    }
  }

  /**
   * Batch callback - translate a single (text) field.
   *
   * @param array $singleField
   *   Chunk of (text) field metadata to translate.
   * @param \Drupal\Core\Language\LanguageInterface $langFrom
   *   The source language.
   * @param \Drupal\Core\Language\LanguageInterface $langTo
   *   The target language.
   * @param array $context
   *   The batch context.
   */
  public function translateSingleField(
    array $singleField,
    LanguageInterface $langFrom,
    LanguageInterface $langTo,
    array &$context,
  ) {
    $translatedField = $this->translationOrchestrator->translateTextMetadataItem($singleField, $langFrom, $langTo);
    if ($translatedField === NULL) {
      $context['results']['failures'][] = $singleField['field_name'] ?? (string) reset($singleField['parents']);
      return;
    }
    $context['results']['processedTranslations'][] = $translatedField;
  }

  /**
   * Batch callback - insert processed texts back into the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Entity to translate.
   * @param string $lang_to
   *   Language code of translation.
   * @param array $context
   *   Text metadata containing both source values and translation.
   */
  public function insertTranslation(
    ContentEntityInterface $entity,
    string $lang_to,
    array &$context,
  ) {
    $result = $this->translationOrchestrator->saveTranslatedEntity(
      $entity,
      $lang_to,
      $context['results']['processedTranslations'] ?? [],
      $context['results']['failures'] ?? [],
    );

    if ($result->isSuccess()) {
      $this->messenger()->addStatus($result->getMessage());
    }
    else {
      $this->messenger()->addError($result->getMessage());
    }
  }

  /**
   * Controller access callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param string $entity_type
   *   Entity type machine name.
   * @param string $entity_id
   *   Entity ID.
   * @param string $lang_to
   *   Language to translate to.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(
    AccountInterface $account,
    string $entity_type,
    string $entity_id,
    string $lang_to,
  ): AccessResultInterface {
    try {
      $storage = $this->entityTypeManager()->getStorage($entity_type);
    }
    catch (PluginNotFoundException | InvalidPluginDefinitionException) {
      return AccessResult::forbidden('Unknown entity type.');
    }

    $entity = $storage->load($entity_id);
    if (!$entity instanceof ContentEntityInterface) {
      return AccessResult::forbidden('Unknown entity.');
    }

    // @todo Allow update of existing translations. For now, only new
    // translations are allowed, so the link is not offered for a language the
    // entity already has.
    if ($entity->hasTranslation($lang_to)) {
      return AccessResult::forbidden('The translation already exists.');
    }

    return $this->translationOrchestrator
      ->checkTranslateAccess($entity, $account, $lang_to);
  }

}
