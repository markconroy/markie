<?php

declare(strict_types=1);

namespace Drupal\ai_automators\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;

/**
 * Contains hook implementations for the ai_automators module.
 */
class AiAutomatorsHooks {

  /**
   * Maximum number of times a queue item is retried before it is dropped.
   */
  const MAX_ATTEMPTS = 3;

  /**
   * Constructs an AiAutomatorsHooks object.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly QueueFactory $queueFactory,
    protected readonly QueueWorkerManagerInterface $queueWorkerManager,
    protected readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Implements hook_cron().
   *
   * Processes queued AI Automator field-fill jobs up to the configured limit.
   */
  #[Hook('cron')]
  public function cron(): void {
    $limit = (int) $this->configFactory
      ->get('ai_automators.settings')
      ->get('queue_cron_items');

    $queue = $this->queueFactory->get('ai_automator_field_modifier');
    $worker = $this->queueWorkerManager->createInstance('ai_automator_field_modifier');

    $processed = 0;
    while (($limit === 0 || $processed < $limit) && ($item = $queue->claimItem(300))) {
      try {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (\Throwable $e) {
        // Bounded retry: count attempts in the item data and drop the item once
        // it exceeds the cap. A blind releaseItem() would let a permanently
        // failing ("poison") item be released and re-claimed forever when the
        // limit is 0, so we delete and re-queue with an incremented counter
        // instead, and stop retrying past MAX_ATTEMPTS.
        // @todo A per-run time budget and a processed seen-set could further
        // bound a single cron run; tracked as follow-ups on #3575190.
        $attempts = (int) ($item->data['_attempts'] ?? 0) + 1;
        $queue->deleteItem($item);
        if ($attempts >= self::MAX_ATTEMPTS) {
          $this->loggerFactory->get('ai_automator')->error(
            'Dropping AI Automator queue item after @attempts failed attempts: @message',
            ['@attempts' => $attempts, '@message' => $e->getMessage()]
          );
        }
        else {
          $queue->createItem(['_attempts' => $attempts] + $item->data);
          $this->loggerFactory->get('ai_automator')->warning(
            'Re-queued AI Automator item (attempt @attempts of @max): @message',
            [
              '@attempts' => $attempts,
              '@max' => self::MAX_ATTEMPTS,
              '@message' => $e->getMessage(),
            ]
          );
        }
      }
      $processed++;
    }
  }

}
