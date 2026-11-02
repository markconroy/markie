<?php

namespace Drupal\ai_automators\Plugin\QueueWorker;

use Drupal\ai_automators\AiAutomatorRuleRunner;
use Drupal\ai_automators\AiAutomatorStatusField;
use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue worker that fills fields for entities from AI data.
 */
#[QueueWorker(
  id: 'ai_automator_field_modifier',
  title: new TranslatableMarkup('Queue Job to fill in AI produced data')
)]
class AutomatorFieldData extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Runner.
   */
  protected AiAutomatorRuleRunner $aiRunner;

  /**
   * The Drupal entity manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Drupal logger factory.
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The Drupal database connection.
   */
  protected Connection $db;

  /**
   * The account switcher.
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai_automators\AiAutomatorRuleRunner $aiRunner
   *   The AI runner.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher.
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, AiAutomatorRuleRunner $aiRunner, EntityTypeManagerInterface $entityTypeManager, LoggerChannelFactoryInterface $loggerFactory, Connection $db, AccountSwitcherInterface $accountSwitcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aiRunner = $aiRunner;
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory;
    $this->db = $db;
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_automator.rule_runner'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('database'),
      $container->get('account_switcher'),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function processItem($data) {
    // Run as the user who queued the item, so the automator has the same
    // access as when it runs directly, instead of the cron user's.
    $account = $this->getQueueingUser($data);
    if (!$account) {
      return;
    }
    $this->accountSwitcher->switchTo($account);
    try {
      return $this->processItemAsCurrentUser($data);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Gets the user who queued the item.
   *
   * The user is resolved when the item is queued, see
   * QueueWorkerProcessor::getRunAsUid(). Items without a usable user are
   * skipped instead of running as the user who processes the queue, who may
   * have more access.
   *
   * @param array $data
   *   The queue item data.
   *
   * @return \Drupal\Core\Session\AccountInterface|null
   *   The account to run the item as, or NULL to skip it.
   */
  protected function getQueueingUser(array $data): ?AccountInterface {
    $context = [
      '%field' => $data['automatorConfig']['field_name'],
      '%entity_type' => $data['entity_type'],
      '%id' => $data['entity_id'],
    ];
    // Items queued before the user was stored in the queue item.
    if (!array_key_exists('uid', $data)) {
      $this->loggerFactory->get('ai_automator')->warning('Skipped the automator for field %field on entity %entity_type with id %id: the queue item has no user. Save the entity again to queue it again.', $context);
      return NULL;
    }
    // Saves without a current user or fallback user, for example from cron.
    if (empty($data['uid'])) {
      return new AnonymousUserSession();
    }
    /** @var \Drupal\user\UserInterface|null $account */
    $account = $this->entityTypeManager->getStorage('user')->load($data['uid']);
    if (!$account || !$account->isActive()) {
      $this->loggerFactory->get('ai_automator')->warning('Skipped the automator for field %field on entity %entity_type with id %id: the user %uid who queued it is missing or blocked.', $context + [
        '%uid' => $data['uid'],
      ]);
      return NULL;
    }
    return $account;
  }

  /**
   * Processes a queue item as the current user.
   *
   * @param array $data
   *   The queue item data.
   *
   * @return mixed
   *   The result of saving the entity, or NULL on failure.
   */
  protected function processItemAsCurrentUser(array $data) {
    $this->loggerFactory->get('ai_automator')->info("Queue worker starting to fill out field %field on entity %entity_type with id %id", [
      '%field' => $data['automatorConfig']['field_name'],
      '%entity_type' => $data['entity_type'],
      '%id' => $data['entity_id'],
    ]);

    try {
      // Get new entity, to not overwrite.
      $newEntity = $this->entityTypeManager->getStorage($data['entity_type'])->load($data['entity_id']);
      // Maybe it was removed.
      if ($newEntity == NULL) {
        $this->loggerFactory->get('ai_automator')->warning('The entity %entity_type with id %id was not found', [
          '%entity_type' => $data['entity_type'],
          '%id' => $data['entity_id'],
        ]);
        return;
      }
      $entity = $this->aiRunner->generateResponse($newEntity, $data['fieldDefinition'], $data['automatorConfig']);
      // Turn off the hook.
      ai_automators_entity_can_save_toggle(FALSE);
      // Check if its the last queue item for the entity and reset processing.
      if ($this->lastInQueue($entity->getEntityTypeId(), $entity->id())) {
        $entity->set('ai_automator_status', AiAutomatorStatusField::STATUS_FINISHED);
      }
      // Resave.
      $success = $entity->save();
      // Turn on the hook.
      ai_automators_entity_can_save_toggle(TRUE);
      $this->loggerFactory->get('ai_automator')->info("Queue worker finished to fill out field %field on entity %entity_type with id %id", [
        '%field' => $data['automatorConfig']['field_name'],
        '%entity_type' => $data['entity_type'],
        '%id' => $data['entity_id'],
      ]);
      return $success;
    }
    catch (AiAutomatorRuleNotFoundException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorRequestErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorResponseErrorException $e) {
      $this->loggerFactory->get('ai_automator')->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('ai_automator')->warning('A general error happened why trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    // Since it failed.
    ai_automators_entity_can_save_toggle(FALSE);
    $entity = $this->entityTypeManager->getStorage($data['entity_type'])->load($data['entity_id']);
    $entity->set('ai_automator_status', AiAutomatorStatusField::STATUS_FAILED);
    $entity->save();
    ai_automators_entity_can_save_toggle(TRUE);
  }

  /**
   * Check if its the last processed item for that entity.
   *
   * @param string $entityType
   *   The entity type.
   * @param int $entityId
   *   The entity id.
   *
   * @return bool
   *   If its the last item.
   */
  protected function lastInQueue($entityType, $entityId) {
    $query = $this->db->select('queue', 'q');
    $query
      ->fields('q', ['data'])
      ->condition('name', 'ai_automator_field_modifier');
    $result = $query->execute();
    // Amount found.
    $amount = 0;
    foreach ($result as $record) {
      $data = unserialize($record->data, ['allowed_classes' => FALSE]);
      if ($data['entity_type'] === $entityType && $data['entity_id'] === $entityId) {
        $amount++;
      }
    }
    // Since itself still counts, 1 item is the last.
    return $amount <= 1;
  }

}
