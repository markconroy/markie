<?php

namespace Drupal\entity_usage;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A service to recreate an entity's tracking data for a field and method.
 *
 * @see \Drupal\entity_usage\EntityUpdateManager::trackUpdateOnEdition()
 * @see \Drupal\entity_usage\Plugin\QueueWorker\RecreateTrackingDataForFieldWorker
 */
class RecreateTrackingDataForFieldQueuer {

  /**
   * Determines if the service has been registered to the event dispatcher.
   *
   * @var bool
   */
  protected bool $listenerRegistered = FALSE;

  /**
   * The records to process or queue on kernel termination.
   *
   * @var array<string, array{entity_type_id: string, entity_id: string, entity_revision_id: string, method: string, field_name: string}>
   */
  protected array $records = [];

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public function __construct(
    #[Autowire(service: 'event_dispatcher')]
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityUsageInterface $entityUsage,
    protected EntityUsageTrackManager $entityUsageTrackManager,
    protected QueueFactory $queueFactory,
    LoggerChannelFactoryInterface $logger_factory,
    #[Autowire(param: 'entity_usage.url_updater_records_to_process')]
    protected int $number_of_records_to_process,
  ) {
    $this->logger = $logger_factory->get('entity_usage');
  }

  /**
   * Adds a record to be processed.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string|int $source_id
   *   The source entity ID.
   * @param string $source_vid
   *   The source entity revision ID.
   * @param string $method
   *   The tracking plugin method.
   * @param string $field_name
   *   The field name.
   */
  public function addRecord(string $entity_type, string|int $source_id, string $source_vid, string $method, string $field_name): void {
    if (!$this->listenerRegistered) {
      $this->eventDispatcher->addListener(KernelEvents::TERMINATE, [$this, 'onKernelTerminate']);
      $this->listenerRegistered = TRUE;
    }
    $record_id = $entity_type . '|' . $source_id . '|' . $source_vid . '|' . $method . '|' . $field_name;
    $this->records[$record_id] = [
      'entity_type_id' => $entity_type,
      'entity_id' => $source_id,
      'entity_revision_id' => $source_vid,
      'method' => $method,
      'field_name' => $field_name,
    ];
  }

  /**
   * Processes or queues records on kernel termination.
   *
   * @param \Symfony\Component\HttpKernel\Event\TerminateEvent $event
   *   The terminate event.
   */
  public function onKernelTerminate(TerminateEvent $event): void {
    for ($i = 0; $i < $this->number_of_records_to_process; $i++) {
      $record = array_shift($this->records);
      if ($record === NULL) {
        // No more records left to process.
        break;
      }
      $this->processRecord(...$record);
    }

    // Queue left over records.
    if (!empty($this->records)) {
      $queue = $this->queueFactory->get('entity_usage_recreate_tracking_data');
      foreach ($this->records as $record) {
        $queue->createItem($record);
      }
    }
  }

  /**
   * Processes a record.
   *
   * If the entity/revision can be loaded, will regenerate the usage data for
   * the source entity, only for the field and method (plugin) provided.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|int $entity_id
   *   The entity ID.
   * @param string $entity_revision_id
   *   The entity revision ID.
   * @param string $method
   *   The tracking plugin method.
   * @param string $field_name
   *   The field name.
   */
  public function processRecord(string $entity_type_id, string|int $entity_id, string $entity_revision_id, string $method, string $field_name): void {
    $plugin = $this->entityUsageTrackManager->createInstance($method);

    $entity = NULL;
    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    if ($storage->getEntityType()->isRevisionable() && $entity_revision_id) {
      assert($storage instanceof RevisionableStorageInterface);

      $entity = $storage->loadRevision($entity_revision_id);
    }
    elseif ($entity_id) {
      $entity = $storage->load($entity_id);
    }

    if ($entity instanceof FieldableEntityInterface) {
      // Call all plugins that want to track entity usages. We need to call this
      // for all translations as well since Drupal stores new revisions for all
      // translations by default when saving an entity.
      if ($entity instanceof TranslatableInterface) {
        foreach ($entity->getTranslationLanguages() as $translation_language) {
          if ($entity->hasTranslation($translation_language->getId())) {
            /** @var \Drupal\Core\Entity\FieldableEntityInterface $translation */
            $translation = $entity->getTranslation($translation_language->getId());
            $plugin->updateTrackingDataForField($translation, $field_name);
          }
        }
      }
      else {
        // Not translatable, just call the plugin with the entity itself.
        $plugin->updateTrackingDataForField($entity, $field_name);
      }
    }
  }

}
