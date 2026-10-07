<?php

namespace Drupal\ai_translate\Drush;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai_translate\EntityTranslationOrchestratorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
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
   * Shared entity translation orchestrator.
   *
   * @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface
   */
  protected EntityTranslationOrchestratorInterface $translationOrchestrator;

  /**
   * Text translation service.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface
   */
  protected TextTranslatorInterface $textTranslator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = new static();
    $instance->languageManager = $container->get('language_manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->translationOrchestrator = $container->get('ai_translate.translation_orchestrator');
    $instance->textTranslator = $container->get('ai_translate.text_translator');
    return $instance;
  }

  /**
   * Create AI-powered translation of an entity.
   */
  #[Command(
    name: 'ai:translate-entity'
  )]
  #[Argument(name: 'entityType', description: 'Entity type (i.e. node)')]
  #[Argument(name: 'entityIds', description: 'Comma-separated entity IDs (i.e 16,18,20,21)')]
  #[Argument(name: 'langFrom', description: 'Source language code (i.e. fr)')]
  #[Argument(name: 'langTo', description: 'Target language code (i.e. en)')]
  public function translateEntities(
    string $entityType,
    string $entityIds,
    string $langFrom,
    string $langTo,
  ) {
    $ids = array_filter(explode(',', $entityIds));
    $entityStorage = $this->entityTypeManager->getStorage($entityType);
    foreach ($entityStorage->loadMultiple($ids) as $entity) {
      $result = $this->translationOrchestrator->translateEntity($entity, $langFrom, $langTo);
      if ($result->translationExists()) {
        $this->messenger()->addMessage($result->getMessage());
        continue;
      }

      if ($result->isSuccess()) {
        $this->messenger()->addStatus($result->getMessage());
        if ($result->getFailures()) {
          $this->messenger()->addWarning($this->t('Some fields could not be translated.'));
        }
      }
      else {
        $this->messenger()->addError($result->getMessage());
      }
    }
  }

  /**
   * Create AI-powered translation of a text.
   */
  #[Command(
    name: 'ai:translate-text'
  )]
  #[Argument(name: 'text', description: 'Text to translate')]
  #[Argument(name: 'langTo', description: 'Target language code (i.e. en)')]
  #[Argument(name: 'langFrom', description: 'Source language code (i.e. fr)')]
  public function translate(
    string $text,
    string $langFrom,
    string $langTo,
  ) {
    static $langNames;
    if (empty($langNames)) {
      $langNames = $this->languageManager->getNativeLanguages();
    }
    try {
      return $this->textTranslator->translateContent($text,
        $langNames[$langTo], $langNames[$langFrom] ?? NULL);
    }
    catch (TranslationException) {
      // Error already logged by text_translate service.
      $this->messenger()->addError($this->t('Error translating content.'));
      return;
    }
  }

}
