<?php

namespace Drupal\entity_usage;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\entity_usage\Events\EntityUsageEvent;
use Drupal\entity_usage\Events\Events;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Manages Entity Usage integration with Batch API.
 */
class EntityUsageBatchManager implements LoggerAwareInterface {

  use LoggerAwareTrait;
  use StringTranslationTrait;

  /**
   * Table name to bulk entity usage data to.
   */
  const BULK_TABLE_NAME = 'entity_usage_bulk';

  /**
   * The size of the batch for the revision queries.
   */
  const REVISION_BATCH_SIZE = 15;

  /**
   * The number of revisions to load when in bulk mode.
   */
  const BULK_BATCH_SIZE = 200;

  /**
   * The number of IDs to load when in bulk mode.
   */
  const BULK_ID_LOAD = 100000;

  /**
   * Creates a EntityUsageBatchManager object.
   */
  final public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    TranslationInterface $stringTranslation,
    private ConfigFactoryInterface $configFactory,
    private ?EntityUsageInterface $entityUsage = NULL,
  ) {
    $this->setStringTranslation($stringTranslation);
    if ($entityUsage === NULL) {
      // @phpstan-ignore-next-line
      $this->entityUsage = \Drupal::service('entity_usage.usage');
    }
  }

  /**
   * Recreate the entity usage statistics.
   *
   * Generate a batch to recreate the statistics for all entities.
   * Note that if we force all statistics to be created, there is no need to
   * separate them between source/target cases. If all entities are going to
   * be re-tracked, tracking all of them as source is enough, because there
   * could never be a target without a source.
   *
   * @param bool $keep_existing_records
   *   (optional) If TRUE, existing usage records won't be deleted. Defaults to
   *   FALSE.
   */
  public function recreate($keep_existing_records = FALSE): void {
    $batch = $this->generateBatch($keep_existing_records);
    batch_set($batch);
  }

  /**
   * Create a batch to process the entity types in bulk.
   *
   * @param bool $keep_existing_records
   *   (optional) If TRUE existing usage records won't be deleted. Defaults to
   *   FALSE.
   *
   * @return array{operations: array<array{callable-string, array}>, finished: callable-string, title: \Drupal\Core\StringTranslation\TranslatableMarkup, progress_message: \Drupal\Core\StringTranslation\TranslatableMarkup, error_message: \Drupal\Core\StringTranslation\TranslatableMarkup}
   *   The batch array.
   */
  public function generateBatch($keep_existing_records = FALSE): array {
    $batch = new BatchBuilder();
    $batch
      ->setTitle($this->t('Updating entity usage statistics.'))
      ->setProgressMessage($this->t('Processed @current of @total entity types.'))
      ->setErrorMessage($this->t('This batch encountered an error.'))
      ->setFinishCallback('\Drupal\entity_usage\EntityUsageBatchManager::batchFinished');

    if (!$keep_existing_records) {
      $batch->addOperation('\Drupal\entity_usage\EntityUsageBatchManager::truncateTable');
    }

    $bulk_mode = !$keep_existing_records && $this->entityUsage instanceof EntityUsageBulkInterface;

    if ($bulk_mode) {
      $batch->addOperation('\Drupal\entity_usage\EntityUsageBatchManager::createBulkTable');
    }

    foreach (self::getEntityTypesToTrack($this->configFactory->get('entity_usage.settings'), $this->entityTypeManager) as $entity_type_id) {
      $batch->addOperation(
        '\Drupal\entity_usage\EntityUsageBatchManager::updateSourcesBatchWorker',
        [$entity_type_id, $keep_existing_records],
      );
    }

    if ($bulk_mode) {
      $batch->addOperation('\Drupal\entity_usage\EntityUsageBatchManager::copyBulkTable');
      $batch->addOperation('\Drupal\entity_usage\EntityUsageBatchManager::triggerEvents');
      $batch->addOperation('\Drupal\entity_usage\EntityUsageBatchManager::dropBulkTable');
    }

    return $batch->toArray();
  }

  /**
   * Batch operation worker to create the bulk loading table.
   */
  public static function createBulkTable(array &$context): void {
    \Drupal::moduleHandler()->loadInclude('entity_usage', 'install');
    $entity_usage_schema = entity_usage_schema();
    $entity_usage_schema['entity_usage']['description'] = 'Copy of the entity_usage table for bulk loading';
    unset($entity_usage_schema['entity_usage']['indexes']);
    $db_schema = \Drupal::database()->schema();
    if ($db_schema->tableExists(static::BULK_TABLE_NAME)) {
      $db_schema->dropTable(static::BULK_TABLE_NAME);
    }
    $db_schema->createTable(static::BULK_TABLE_NAME, $entity_usage_schema['entity_usage']);
    $context['message'] = t('Created the entity usage bulk table');
  }

  /**
   * Batch operation worker to copy the bulk loading table.
   */
  public static function copyBulkTable(array &$context): void {
    \Drupal::moduleHandler()->loadInclude('entity_usage', 'install');
    $database = \Drupal::database();
    $query = match ($database->databaseType()) {
      'sqlite' => 'INSERT OR IGNORE INTO {entity_usage} SELECT * FROM {' . static::BULK_TABLE_NAME . '};',
      'pgsql' => 'INSERT INTO {entity_usage} SELECT * FROM {' . static::BULK_TABLE_NAME . '} ON CONFLICT DO NOTHING;',
      'mysql' => 'INSERT IGNORE INTO {entity_usage} SELECT * FROM {' . static::BULK_TABLE_NAME . '};',
      default => 'INSERT INTO {entity_usage} SELECT * FROM {' . static::BULK_TABLE_NAME . '};',
    };
    $database->query($query)->execute();
    $context['message'] = t('Loaded the entity usage table from the bulk table');
  }

  /**
   * Batch operation to trigger events after bulk loading.
   */
  public static function triggerEvents(array &$context): void {
    $database = \Drupal::database();
    /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $dispatcher */
    $dispatcher = \Drupal::service('event_dispatcher');
    $dispatch_event = !$dispatcher instanceof EventDispatcherInterface || $dispatcher->hasListeners(Events::USAGE_REGISTER);
    if ($dispatch_event) {
      if (empty($context['sandbox']['total'])) {
        $context['sandbox']['progress'] = 0;
        $context['sandbox']['total'] = $database->select(static::BULK_TABLE_NAME)->countQuery()->execute()->fetchField();
      }
      // Should we trust that the database will return in a consistent order?
      $results = $database
        ->select(static::BULK_TABLE_NAME)
        ->fields(static::BULK_TABLE_NAME)
        ->range($context['sandbox']['progress'], 200)
        ->execute()
        ->fetchAll(\PDO::FETCH_ASSOC);
      foreach ($results as $insert) {
        $context['sandbox']['progress']++;
        $event = new EntityUsageEvent($insert['target_id'], $insert['target_type'], $insert['source_id'], $insert['source_type'], $insert['source_langcode'], $insert['source_vid'], $insert['method'], $insert['field_name'], $insert['count']);
        $dispatcher->dispatch($event, Events::USAGE_REGISTER);
      }

      if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
        $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
      }
      else {
        $context['finished'] = 1;
      }

      $context['message'] = t('Triggering entity usage insert event: @current of @total', [
        '@current' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['total'],
      ]);
    }
  }

  /**
   * Batch operation worker to drop the bulk loading table.
   */
  public static function dropBulkTable(array &$context): void {
    $db_schema = \Drupal::database()->schema();
    if ($db_schema->tableExists(static::BULK_TABLE_NAME)) {
      $db_schema->dropTable(static::BULK_TABLE_NAME);
    }
    $context['message'] = t('Dropped the entity usage bulk table');
  }

  /**
   * Batch operation worker to truncate the table.
   */
  public static function truncateTable(array &$context): void {
    $service = \Drupal::service('entity_usage.usage');
    if ($service instanceof EntityUsageBulkInterface) {
      \Drupal::service('entity_usage.usage')->truncateTable();
      $context['message'] = t('Truncated the entity usage table');
    }
  }

  /**
   * Batch operation worker for recreating statistics for source entities.
   *
   * @param string $entity_type_id
   *   The entity type id, for example 'node'.
   * @param bool $keep_existing_records
   *   If TRUE existing usage records won't be deleted.
   * @param array{sandbox: array{progress?: int, total?: int, current_item?: int}, results: int[], finished: int|float, message: string|\Drupal\Core\StringTranslation\TranslatableMarkup} $context
   *   Batch context.
   */
  public static function updateSourcesBatchWorker($entity_type_id, $keep_existing_records, &$context): void {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $entity_storage = \Drupal::entityTypeManager()->getStorage($entity_type->id());
    $entity_usage = \Drupal::service('entity_usage.usage');

    $bulk_mode = !$keep_existing_records && $entity_usage instanceof EntityUsageBulkInterface;

    match(TRUE) {
      $bulk_mode && $entity_type->isRevisionable() && $entity_storage instanceof RevisionableStorageInterface => static::doBulkRevisionable($entity_storage, $entity_usage, $entity_type, $context),
      $bulk_mode && !$entity_type->isRevisionable() => static::doBulkNonRevisionable($entity_storage, $entity_usage, $entity_type, $context),
      default => static::doOneByOne($entity_storage, $entity_type, $context)
    };

    if ($context['sandbox']['progress'] < $context['sandbox']['total']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['total'];
    }
    else {
      $context['finished'] = 1;
    }

    $context['message'] = t('Updating entity usage for @entity_type: @current of @total', [
      '@entity_type' => $entity_type_id,
      '@current' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['total'],
    ]);

    if ($context['finished'] === 1) {
      // Record the total so we can say how many records we have processed.
      $context['results'][] = $context['sandbox']['total'];
    }
  }

  /**
   * Process multiple revisions in one go.
   *
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\entity_usage\EntityUsageBulkInterface $entity_usage
   *   The entity usage service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param array{sandbox: array{progress?: int, total?: int, current_item?: int}, results: int[], finished: int|float, message: string|\Drupal\Core\StringTranslation\TranslatableMarkup} $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function doBulkRevisionable(RevisionableStorageInterface $entity_storage, EntityUsageInterface $entity_usage, EntityTypeInterface $entity_type, array &$context): void {
    $entity_type_key = $entity_type->getKey('revision');
    if (empty($context['sandbox']['total'])) {
      $id_definition = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type->id())[$entity_type_key];

      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = '';
      if (($id_definition instanceof FieldStorageDefinitionInterface) && $id_definition->getType() === 'integer') {
        $context['sandbox']['current_id'] = -1;
      }
      $context['sandbox']['revision_ids'] = array_keys(
        $entity_storage->getQuery()->allRevisions()
          ->accessCheck(FALSE)
          ->sort($entity_type->getKey('revision'), 'ASC')
          ->range(0, static::BULK_ID_LOAD)
          ->execute()
      );
      $context['sandbox']['total'] = $entity_storage->getQuery()->allRevisions()
        ->accessCheck(FALSE)
        ->sort($entity_type->getKey('revision'), 'ASC')
        ->count()
        ->execute();
    }

    $revision_ids = array_slice($context['sandbox']['revision_ids'], 0, static::BULK_BATCH_SIZE);
    $context['sandbox']['revision_ids'] = array_slice($context['sandbox']['revision_ids'], static::BULK_BATCH_SIZE);
    if (!empty($revision_ids)) {
      /** @var \Drupal\entity_usage\EntityUsageBulkInterface $entity_usage */
      $entity_usage->enableBulkInsert('entity_usage_bulk');

      try {
        foreach ($entity_storage->loadMultipleRevisions($revision_ids) as $entity_revision) {
          $revision_id = $entity_revision->getRevisionId();
          \Drupal::service('entity_usage.entity_update_manager')->trackUpdateOnCreation($entity_revision);
          $context['sandbox']['current_id'] = $revision_id;
        }
        $entity_usage->bulkInsert();
      }
      catch (\Exception $e) {
        Error::logException(\Drupal::service('logger.channel.entity_usage'), $e);
      }
    }
    $context['sandbox']['progress'] += count($revision_ids);

    if ($context['sandbox']['progress'] === $context['sandbox']['total']) {
      // Recalculate the total so that any new revisions created while bulk
      // processing are included.
      $context['sandbox']['revision_ids'] = array_keys(
        $entity_storage->getQuery()->allRevisions()
          ->condition($entity_type_key, $context['sandbox']['current_id'], '>')
          ->accessCheck(FALSE)
          ->sort($entity_type->getKey('revision'), 'ASC')
          ->range(0, static::BULK_BATCH_SIZE)
          ->execute()
      );
      $context['sandbox']['total'] = $context['sandbox']['total'] + count($context['sandbox']['revision_ids']);
    }
    elseif (empty($context['sandbox']['revision_ids'])) {
      $context['sandbox']['revision_ids'] = array_keys(
        $entity_storage->getQuery()->allRevisions()
          ->condition($entity_type_key, $context['sandbox']['current_id'], '>')
          ->accessCheck(FALSE)
          ->sort($entity_type->getKey('revision'), 'ASC')
          ->range(0, static::BULK_ID_LOAD)
          ->execute()
      );
      $context['sandbox']['total'] = $entity_storage->getQuery()->allRevisions()
        ->accessCheck(FALSE)
        ->sort($entity_type->getKey('revision'), 'ASC')
        ->count()
        ->execute();
    }
  }

  /**
   * Process multiple non-revisionable entities in one go.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\entity_usage\EntityUsageBulkInterface $entity_usage
   *   The entity usage service.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param array{sandbox: array{progress?: int, total?: int, current_item?: int}, results: int[], finished: int|float, message: string|\Drupal\Core\StringTranslation\TranslatableMarkup} $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function doBulkNonRevisionable(EntityStorageInterface $entity_storage, EntityUsageInterface $entity_usage, EntityTypeInterface $entity_type, array &$context): void {
    $entity_type_key = $entity_type->getKey('id');
    if (empty($context['sandbox']['total'])) {
      $id_definition = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type->id())[$entity_type_key];

      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = '';
      if (($id_definition instanceof FieldStorageDefinitionInterface) && $id_definition->getType() === 'integer') {
        $context['sandbox']['current_id'] = -1;
      }
      $context['sandbox']['entity_ids'] = $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->range(0, static::BULK_ID_LOAD)
        ->sort($entity_type_key)
        ->execute();
      $context['sandbox']['total'] = $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->sort($entity_type_key)
        ->execute();
    }

    $entity_ids = $entity_storage->getQuery()
      ->condition($entity_type_key, $context['sandbox']['current_id'], '>')
      ->range(0, static::BULK_BATCH_SIZE)
      ->accessCheck(FALSE)
      ->sort($entity_type_key)
      ->execute();

    if (!empty($entity_ids)) {
      $entity_usage->enableBulkInsert('entity_usage_bulk');
      try {
        foreach ($entity_storage->loadMultiple($entity_ids) as $entity) {
          // Sources are tracked as if they were new entities.
          \Drupal::service('entity_usage.entity_update_manager')->trackUpdateOnCreation($entity);
          $context['sandbox']['current_id'] = $entity->id();
        }
        $entity_usage->bulkInsert();
      }
      catch (\Exception $e) {
        Error::logException(\Drupal::service('logger.channel.entity_usage'), $e);
      }
    }
    $context['sandbox']['progress'] += count($entity_ids);

    if ($context['sandbox']['progress'] === $context['sandbox']['total']) {
      // Recalculate the total so that any new entities created while bulk
      // processing are included.
      $context['sandbox']['entity_ids'] = $entity_storage->getQuery()
        ->condition($entity_type_key, $context['sandbox']['current_id'], '>')
        ->range(0, static::BULK_BATCH_SIZE)
        ->accessCheck(FALSE)
        ->sort($entity_type_key)
        ->execute();
      $context['sandbox']['total'] = $context['sandbox']['total'] + count($context['sandbox']['entity_ids']);
    }
    elseif (empty($context['sandbox']['entity_ids'])) {
      $context['sandbox']['entity_ids'] = $entity_storage->getQuery()
        ->condition($entity_type_key, $context['sandbox']['current_id'], '>')
        ->accessCheck(FALSE)
        ->range(0, static::BULK_ID_LOAD)
        ->sort($entity_type_key)
        ->execute();
      $context['sandbox']['total'] = $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->sort($entity_type_key)
        ->execute();
    }
  }

  /**
   * Process each entity one at a time.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   * @param array{sandbox: array{progress?: int, total?: int, current_item?: int}, results: int[], finished: int|float, message: string|\Drupal\Core\StringTranslation\TranslatableMarkup} $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function doOneByOne(EntityStorageInterface $entity_storage, EntityTypeInterface $entity_type, array &$context): void {
    $entity_type_key = $entity_type->getKey('id');
    $entity_type_id = $entity_type->id();
    if (empty($context['sandbox']['total'])) {
      $id_definition = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id)[$entity_type_key];

      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_id'] = '';
      if (($id_definition instanceof FieldStorageDefinitionInterface) && $id_definition->getType() === 'integer') {
        $context['sandbox']['current_id'] = -1;
      }
      $context['sandbox']['total'] = (int) $entity_storage->getQuery()
        ->accessCheck(FALSE)
        ->count()
        ->execute();
      $context['sandbox']['batch_entity_revision'] = [
        'status' => 0,
        'current_vid' => 0,
        'start' => 0,
      ];
    }
    if ($context['sandbox']['batch_entity_revision']['status']) {
      $op = '=';
    }
    else {
      $op = '>';
    }

    $entity_ids = $entity_storage->getQuery()
      ->condition($entity_type_key, $context['sandbox']['current_id'], $op)
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->sort($entity_type_key)
      ->execute();
    $entity_id = reset($entity_ids);

    if ($entity_id !== FALSE) {
      try {
        if ($entity_type->isRevisionable()) {
          assert($entity_storage instanceof RevisionableStorageInterface);

          // We cannot query the revisions due to this bug
          // https://www.drupal.org/project/drupal/issues/2766135
          // so we will use offsets.
          $start = $context['sandbox']['batch_entity_revision']['start'];
          // Track all revisions and translations of the source entity. Sources
          // are tracked as if they were new entities.
          $result = $entity_storage->getQuery()->allRevisions()
            ->condition($entity_type->getKey('id'), $entity_id)
            ->accessCheck(FALSE)
            ->sort($entity_type->getKey('revision'), 'DESC')
            ->range($start, static::REVISION_BATCH_SIZE)
            ->execute();
          $revision_ids = array_keys($result);
          if (count($revision_ids) === static::REVISION_BATCH_SIZE) {
            $context['sandbox']['batch_entity_revision'] = [
              'status' => 1,
              'current_vid' => min($revision_ids),
              'start' => $start + static::REVISION_BATCH_SIZE,
            ];
          }
          else {
            $context['sandbox']['batch_entity_revision'] = [
              'status' => 0,
              'current_vid' => 0,
              'start' => 0,
            ];
          }

          foreach ($entity_storage->loadMultipleRevisions($revision_ids) as $entity_revision) {
            /** @var \Drupal\Core\Entity\EntityInterface $entity_revision */
            \Drupal::service('entity_usage.entity_update_manager')->trackUpdateOnCreation($entity_revision);
          }
        }
        else {
          // Sources are tracked as if they were new entities.
          $entity = $entity_storage->load($entity_id);
          \Drupal::service('entity_usage.entity_update_manager')->trackUpdateOnCreation($entity);
        }
      }
      catch (\Exception $e) {
        Error::logException(\Drupal::service('logger.channel.entity_usage'), $e);
      }

      if (
        $context['sandbox']['batch_entity_revision']['status'] === 0 ||
        intval($context['sandbox']['progress']) === 0
      ) {
        $context['sandbox']['progress']++;
      }
      $context['sandbox']['current_id'] = $entity_id;
    }
  }

  /**
   * Gets the list of entity type IDs to track.
   *
   * @param \Drupal\Core\Config\Config $entity_usage_config
   *   The entity usage config.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   *
   * @return string[]
   *   The list of entity type IDs to track.
   */
  private static function getEntityTypesToTrack(Config $entity_usage_config, EntityTypeManagerInterface $entity_type_manager): array {
    $entity_types = [];
    $to_track = $entity_usage_config->get('track_enabled_source_entity_types');
    foreach (\Drupal::entityTypeManager()->getDefinitions() as $entity_type_id => $entity_type) {
      // Only look for entities enabled for tracking on the settings form.
      if (!is_array($to_track) && ($entity_type->entityClassImplements('\Drupal\Core\Entity\ContentEntityInterface'))) {
        // When no settings are defined, track all content entities by default,
        // except for Files and Users.
        if (!in_array($entity_type_id, ['file', 'user'])) {
          $entity_types[] = $entity_type_id;
        }
      }
      elseif (is_array($to_track) && in_array($entity_type_id, $to_track, TRUE)) {
        $entity_types[] = $entity_type_id;
      }
    }
    return $entity_types;
  }

  /**
   * Finish callback for our batch processing.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param mixed[] $results
   *   The results array.
   * @param mixed[] $operations
   *   The operations array.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      \Drupal::messenger()->addMessage(t('Recreated entity usage for @count entities.', ['@count' => array_sum($results)]));
    }
    else {
      // An error occurred.
      // $operations contains the operations that remained unprocessed.
      $error_operation = reset($operations);
      \Drupal::messenger()->addMessage(
        t('An error occurred while processing @operation with arguments : @args',
          [
            '@operation' => $error_operation[0],
            '@args' => print_r($error_operation[0], TRUE),
          ]
        )
      );
    }
  }

}
