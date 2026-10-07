<?php

namespace Drupal\ai_translate_tool\Plugin\tool\Tool;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_translate\EntityTranslationOrchestratorInterface;
use Drupal\tool\Attribute\Tool;
use Drupal\tool\ExecutableResult;
use Drupal\tool\Tool\ToolBase;
use Drupal\tool\Tool\ToolOperation;
use Drupal\tool\TypedData\InputDefinition;
use Drupal\tool\TypedData\ListContextDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exposes entity translation through the Tool API.
 */
#[Tool(
  id: 'ai_translate_tool:translate_entity',
  label: new TranslatableMarkup('Translate entity'),
  description: new TranslatableMarkup('Translate a translatable content entity into another language.'),
  operation: ToolOperation::Write,
  input_definitions: [
    'entity_type_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type ID'),
      description: new TranslatableMarkup('The content entity type machine name to translate, for example node.'),
      required: TRUE,
      constraints: [
        'PluginExists' => [
          'manager' => 'entity_type.manager',
          'interface' => ContentEntityInterface::class,
        ],
      ],
    ),
    'entity_id' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The entity ID of the entity to translate.'),
      required: TRUE,
    ),
    'target_language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Target language'),
      description: new TranslatableMarkup('The language code to translate the entity into.'),
      required: TRUE,
      constraints: [
        'Choice' => [
          'callback' => [self::class, 'getAvailableLanguageCodes'],
        ],
      ],
    ),
    'source_language' => new InputDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source language'),
      description: new TranslatableMarkup('The source language code to translate from. If omitted, the entity language is used.'),
      required: FALSE,
      constraints: [
        'Choice' => [
          'callback' => [self::class, 'getAvailableLanguageCodes'],
        ],
      ],
    ),
  ],
  output_definitions: [
    'status' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Status'),
      description: new TranslatableMarkup('The translation execution status.'),
    ),
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Message'),
      description: new TranslatableMarkup('The translation execution summary.'),
    ),
    'entity_type_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type ID'),
      description: new TranslatableMarkup('The translated entity type machine name.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      description: new TranslatableMarkup('The translated entity ID.'),
    ),
    'source_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source language'),
      description: new TranslatableMarkup('The source language code used for translation.'),
    ),
    'target_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Target language'),
      description: new TranslatableMarkup('The target language code used for translation.'),
    ),
    'translated_entity_label' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Translated entity label'),
      description: new TranslatableMarkup('The translated entity label, if a translation was created.'),
    ),
    'failures' => new ListContextDefinition(
      label: new TranslatableMarkup('Failures'),
      description: new TranslatableMarkup('Any field-level translation failures.'),
      item_definition: new ContextDefinition(
        data_type: 'string',
        label: new TranslatableMarkup('Failure'),
        description: new TranslatableMarkup('A field-level translation failure.'),
      ),
    ),
  ],
)]
class TranslateEntityTool extends ToolBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The translation orchestrator.
   *
   * @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface
   */
  protected EntityTranslationOrchestratorInterface $translationOrchestrator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->translationOrchestrator = $container->get(EntityTranslationOrchestratorInterface::class);
    return $instance;
  }

  /**
   * Returns available language codes for tool input validation.
   *
   * @return string[]
   *   The available language codes.
   */
  public static function getAvailableLanguageCodes(): array {
    return array_keys(\Drupal::languageManager()->getLanguages());
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(array $values): ExecutableResult {
    $entityTypeId = $values['entity_type_id'];
    $entityId = $values['entity_id'];
    $targetLanguage = $values['target_language'];
    $sourceLanguage = $values['source_language'] ?? '';

    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return ExecutableResult::failure(new TranslatableMarkup('The requested entity type does not exist.'));
    }

    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return ExecutableResult::failure(new TranslatableMarkup('The requested entity was not found or is not a content entity.'));
    }

    if (!$entity->getEntityType()->isTranslatable()) {
      return ExecutableResult::failure(new TranslatableMarkup('The requested entity type is not translatable.'));
    }

    if ($sourceLanguage === '') {
      $sourceLanguage = $entity->language()->getId();
    }

    $result = $this->translationOrchestrator->translateEntity($entity, $sourceLanguage, $targetLanguage);
    $translatedEntity = $result->getTranslatedEntity();

    return $result->isSuccess() || $result->translationExists()
      ? ExecutableResult::success(
        new TranslatableMarkup('@message', ['@message' => $result->getMessage()]),
        [
          'status' => $result->translationExists() ? 'skipped' : 'success',
          'message' => (string) $result->getMessage(),
          'entity_type_id' => $entityTypeId,
          'entity_id' => $entityId,
          'source_language' => $sourceLanguage,
          'target_language' => $targetLanguage,
          'translated_entity_label' => $translatedEntity ? ($translatedEntity->label() ?? '') : '',
          'failures' => $result->getFailures(),
        ],
      )
      : ExecutableResult::failure(
        new TranslatableMarkup('@message', ['@message' => $result->getMessage()]),
        [
          'status' => 'failed',
          'message' => (string) $result->getMessage(),
          'entity_type_id' => $entityTypeId,
          'entity_id' => $entityId,
          'source_language' => $sourceLanguage,
          'target_language' => $targetLanguage,
          'translated_entity_label' => '',
          'failures' => $result->getFailures(),
        ],
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(array $values, AccountInterface $account, bool $return_as_object = FALSE): bool|AccessResultInterface {
    $entityTypeId = $values['entity_type_id'] ?? '';
    $entityId = $values['entity_id'] ?? NULL;

    if (!$this->entityTypeManager->hasDefinition($entityTypeId)) {
      return $return_as_object ? AccessResult::forbidden('Unknown entity type.') : FALSE;
    }

    $entity = $this->entityTypeManager->getStorage($entityTypeId)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      return $return_as_object ? AccessResult::forbidden('Unknown entity.') : FALSE;
    }

    $access = $this->translationOrchestrator->checkTranslateAccess(
      $entity,
      $account,
      $values['target_language'] ?? '',
    );
    return $return_as_object ? $access : $access->isAllowed();
  }

}
