<?php

namespace Drupal\ai_translate\Drush;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_translate\Controller\AiTranslateController;
use Drupal\ai_translate\TextExtractorInterface;
use Drush\Attributes\Argument;
use Drush\Attributes\Command;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI translate drush commands.
 */
class AiTranslateCommands extends DrushCommands {

  use LoggerChannelTrait;
  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Text extractor.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * Translate controller.
   *
   * @var \Drupal\ai_translate\Controller\AiTranslateController
   */
  protected AiTranslateController $translateController;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new static();
    $instance->languageManager = $container->get('language_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->textExtractor = $container->get('ai_translate.text_extractor');
    $instance->translateController = \Drupal::classResolver(AiTranslateController::class
    );
    return $instance;
  }

  /**
   * Create AI-powered translation of an entity.
   */
  #[Command(
    name: 'ai:translate'
  )]
  #[Argument(name: 'entityType', description: 'Entity type (i.e. node)')]
  #[Argument(name: 'entityId', description: 'Entity ID (i.e. 16)')]
  #[Argument(name: 'langFrom', description: 'Source language code (i.e. fr)')]
  #[Argument(name: 'langTo', description: 'Target language code (i.e. en)')]
  public function translate(
    string $entityType,
    string $entityId,
    string $langFrom,
    string $langTo,
  ) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    $langFromName = $langNames[$langFrom]->getName();
    $langToName = $langNames[$langTo]->getName();
    $entity = $this->entityTypeManager->getStorage($entityType)
      ->load($entityId);
    if ($entity->language()->getId() !== $langFrom
      && $entity->hasTranslation($langFrom)) {
      $entity = $entity->getTranslation($langFrom);
    }
    if ($entity->hasTranslation($langTo)) {
      $this->messenger()->addMessage(
        $this->t('Translation already exists.'));
      return;
    }
    $textMetadata = $this->textExtractor->extractTextMetadata($entity);
    foreach ($textMetadata as &$singleText) {
      $singleText['translated'] = $this->translateController
        ->translateContent($singleText['value'], $langFromName, $langToName);
    }
    $translation = $entity->addTranslation($langTo);
    $this->textExtractor->insertTextMetadata($translation,
      $textMetadata);
    try {
      $translation->save();
      $this->messenger()->addStatus($this->t('Content translated successfully.'));
    }
    catch (\Throwable $exception) {
      $this->getLogger('ai_translate')->warning($exception->getMessage());
      $this->messenger()->addError($this->t('There was some issue with content translation.'));
    }

  }

}
