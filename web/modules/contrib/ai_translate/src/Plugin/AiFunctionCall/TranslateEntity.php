<?php

namespace Drupal\ai_translate\Plugin\AiFunctionCall;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Drupal\ai_translate\EntityTranslationOrchestratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of entity translation for AI function calling.
 */
#[FunctionCall(
  id: 'ai_translate:translate_entity',
  function_name: 'ai_translate_translate_entity',
  name: 'Translate entity',
  description: 'Translate a translatable content entity into another language.',
  group: 'ai_translate',
  context_definitions: [
    'entity_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity type'),
      required: TRUE,
      description: new TranslatableMarkup('The content entity type to translate, for example node.'),
    ),
    'entity_id' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Entity ID'),
      required: TRUE,
      description: new TranslatableMarkup('The entity ID of the entity to translate.'),
    ),
    'target_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Target language'),
      required: TRUE,
      description: new TranslatableMarkup('The language code to translate the entity into.'),
    ),
    'source_language' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Source language'),
      required: FALSE,
      description: new TranslatableMarkup('The source language code to translate from. If omitted, the entity language is used.'),
      default_value: '',
    ),
  ],
)]
class TranslateEntity extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The shared translation orchestrator.
   *
   * @var \Drupal\ai_translate\EntityTranslationOrchestratorInterface
   */
  protected EntityTranslationOrchestratorInterface $translationOrchestrator;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->translationOrchestrator = $container->get(EntityTranslationOrchestratorInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $entityType = $this->getContextValue('entity_type');
    $entityId = $this->getContextValue('entity_id');
    $targetLanguage = $this->getContextValue('target_language');
    $sourceLanguage = $this->getContextValue('source_language') ?: '';

    if (!$this->entityTypeManager->hasDefinition($entityType)) {
      $this->setFailureOutput('failed', 'The requested entity type does not exist.', $entityType, $entityId, $sourceLanguage, $targetLanguage);
      return;
    }

    $entity = $this->entityTypeManager->getStorage($entityType)->load($entityId);
    if (!$entity instanceof ContentEntityInterface) {
      $this->setFailureOutput('failed', 'The requested entity was not found or is not a content entity.', $entityType, $entityId, $sourceLanguage, $targetLanguage);
      return;
    }

    if (!$entity->getEntityType()->isTranslatable()) {
      $this->setFailureOutput('failed', 'The requested entity type is not translatable.', $entityType, $entityId, $sourceLanguage, $targetLanguage);
      return;
    }

    $access = $this->access($entity);
    if (!$access->isAllowed()) {
      $this->setFailureOutput('access_denied', 'Access denied: you do not have permission to translate this entity.', $entityType, $entityId, $sourceLanguage, $targetLanguage);
      return;
    }

    if ($sourceLanguage === '') {
      $sourceLanguage = $entity->language()->getId();
    }

    $result = $this->translationOrchestrator->translateEntity($entity, $sourceLanguage, $targetLanguage);
    if ($result->translationExists()) {
      $this->setOutput($this->buildReadableOutput('skipped', (string) $result->getMessage()));
      $this->setStructuredOutput([
        'status' => 'skipped',
        'message' => (string) $result->getMessage(),
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'source_language' => $sourceLanguage,
        'target_language' => $targetLanguage,
        'translated_entity_label' => '',
        'failures' => [],
      ]);
      return;
    }

    $status = $result->isSuccess() ? 'success' : 'failed';
    $translatedEntity = $result->getTranslatedEntity();
    $translatedEntityLabel = $translatedEntity ? ($translatedEntity->label() ?? '') : '';
    $this->setOutput($this->buildReadableOutput($status, (string) $result->getMessage(), $translatedEntityLabel, $result->getFailures()));
    $this->setStructuredOutput([
      'status' => $status,
      'message' => (string) $result->getMessage(),
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'source_language' => $sourceLanguage,
      'target_language' => $targetLanguage,
      'translated_entity_label' => $translatedEntityLabel,
      'failures' => $result->getFailures(),
    ]);
  }

  /**
   * Builds a readable execution summary.
   *
   * @param string $status
   *   The execution status.
   * @param string $message
   *   The result message.
   * @param string $translatedEntityLabel
   *   The translated entity label.
   * @param array $failures
   *   The field-level failures.
   *
   * @return string
   *   The readable output.
   */
  protected function buildReadableOutput(string $status, string $message, string $translatedEntityLabel = '', array $failures = []): string {
    $output = "Entity translation status: {$status}\n";
    $output .= "Message: {$message}";
    if ($translatedEntityLabel !== '') {
      $output .= "\nTranslated entity label: {$translatedEntityLabel}";
    }
    if ($failures !== []) {
      $output .= "\nField failures: " . implode(', ', $failures);
    }
    return $output;
  }

  /**
   * Sets failed output values.
   *
   * @param string $status
   *   The execution status.
   * @param string $message
   *   The readable failure message.
   * @param string $entityType
   *   The entity type.
   * @param string|int|null $entityId
   *   The entity ID.
   * @param string $sourceLanguage
   *   The source language code.
   * @param string $targetLanguage
   *   The target language code.
   */
  protected function setFailureOutput(string $status, string $message, string $entityType, string|int|null $entityId, string $sourceLanguage, string $targetLanguage): void {
    $this->setOutput($this->buildReadableOutput($status, $message));
    $this->setStructuredOutput([
      'status' => $status,
      'message' => $message,
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      'source_language' => $sourceLanguage,
      'target_language' => $targetLanguage,
      'translated_entity_label' => '',
      'failures' => [],
    ]);
  }

  /**
   * Checks whether the current user may translate the entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to translate.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The combined access result.
   */
  protected function access(ContentEntityInterface $entity): AccessResultInterface {
    return $this->translationOrchestrator->checkTranslateAccess(
      $entity,
      $this->currentUser,
      $this->getContextValue('target_language'),
    );
  }

}
