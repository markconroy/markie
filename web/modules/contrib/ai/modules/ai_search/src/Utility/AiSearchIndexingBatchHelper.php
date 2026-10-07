<?php

namespace Drupal\ai_search\Utility;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Utility\IndexingBatchHelper;
use Psr\Log\LoggerInterface;

/**
 * Overrides the Indexing Batch Helper to support chunk-based progress.
 *
 * This results in something like the below when item 1 and 2 exceed the chunk
 * size per batch. The items one and two take multiple batch runs, but the
 * percentage continues to increase, just at a slower rate.
 * - [notice] Processed 12.5% of items on Test index (1 / 8 items).
 * - [notice] Processed 19.4% of items on Test index (1 / 8 items).
 * - [notice] Processed 26.6% of items on Test index (2 / 8 items).
 * - [notice] Processed 34.4% of items on Test index (2 / 8 items).
 * - [notice] Processed 50.0% of items on Test index (4 / 8 items).
 * - [notice] Processed 75.0% of items on Test index (6 / 8 items).
 * - [notice] Processed 100.0% of items on Test index (8 / 8 items).
 * - [notice] Message: Successfully indexed 8 items.
 */
class AiSearchIndexingBatchHelper extends IndexingBatchHelper {

  /**
   * Constructs the AI Search Indexing Batch Helper.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lockBackend
   *   The lock backend.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(
    protected LockBackendInterface $lockBackend,
    TranslationInterface $stringTranslation,
    protected ConfigFactoryInterface $configFactory,
    protected TimeInterface $time,
    protected MessengerInterface $messenger,
    protected LoggerInterface $logger,
    protected Connection $database,
  ) {
    parent::__construct(
      $lockBackend,
      $stringTranslation,
      $configFactory,
      $time,
      $messenger,
      $logger
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(
    IndexInterface $index,
    int $batch_size,
    int $limit,
    int $time_limit,
    array|\ArrayAccess &$context,
  ): void {

    // Check if the sandbox should be initialized.
    if (!isset($context['sandbox']['limit'])) {
      // Initialize the sandbox with data which is shared among the batch runs.
      $context['sandbox']['limit'] = $limit;
      $context['sandbox']['batch_size'] = $batch_size;
      if ($time_limit >= 0) {
        $context['sandbox']['time_limit'] = $time_limit;
        $context['sandbox']['time_start'] = time();
      }

      // Initialize the results with data which is shared among the batch runs.
      $context['results']['indexed'] = 0.0;
      $context['results']['not indexed'] = 0;

      // Get the remaining item count. When no valid tracker is available, then
      // the value will be set to zero, which will cause the batch process to
      // stop.
      $remaining_item_count = (int) $index->getTrackerInstanceIfAvailable()?->getRemainingItemsCount();

      // Check if an explicit limit needs to be used.
      if ($context['sandbox']['limit'] > -1) {
        $actual_limit = min($context['sandbox']['limit'], $remaining_item_count);
      }
      else {
        // Use the remaining item count as the actual limit.
        $actual_limit = $remaining_item_count;
      }

      // Store original count of items to be indexed to show progress properly.
      $context['sandbox']['original_item_count'] = $actual_limit;

      if ($actual_limit == 0) {
        $context['finished'] = 1;
        return;
      }
    }

    $tracker = $index->getTrackerInstanceIfAvailable();
    if (!$tracker) {
      $context['finished'] = 1;
      return;
    }

    // Time limit check.
    if (($context['sandbox']['time_limit'] ?? -1) >= 0) {
      $elapsed_seconds = time() - $context['sandbox']['time_start'];
      if ($elapsed_seconds > $context['sandbox']['time_limit']) {
        $context['finished'] = 1;
        $context['message'] = $this->t('Time limit of @time_limit seconds reached during indexing on @index', [
          '@time_limit' => $context['sandbox']['time_limit'],
          '@index' => $index->label(),
        ]);
        return;
      }
    }

    // Determine the number of items to *attempt* to index for this run.
    $remaining_item_count = $tracker->getRemainingItemsCount();
    if ($context['sandbox']['limit'] > -1) {
      $actual_limit = min(
        $context['sandbox']['limit'] - floor($context['results']['indexed']),
        $remaining_item_count
      );
    }
    else {
      $actual_limit = $remaining_item_count;
    }

    if ($actual_limit <= 0) {
      $remaining_item_count = 0;
    }

    $to_index = $actual_limit;
    if ($context['sandbox']['batch_size'] > 0) {
      $to_index = min($actual_limit, $context['sandbox']['batch_size']);
    }

    // Catch any exception that may occur during indexing.
    try {
      if ($to_index > 0 || $remaining_item_count > 0) {
        // Index items. We don't use the integer return value, as we'll
        // calculate fractional progress ourselves.
        $index->indexItems($to_index);
      }

      // AI Search: Recalculate total progress based on chunks.
      $original_count = (float) $context['sandbox']['original_item_count'];
      if ($original_count == 0) {
        $context['finished'] = 1;
        return;
      }

      // 1. Get progress from *fully completed* items.
      $current_remaining_item_count = $tracker->getRemainingItemsCount();
      $fully_completed_count = $original_count - $current_remaining_item_count;

      // 2. Get progress from *partially completed* items.
      // We sum the progress of all items that are started but not finished
      // (processed_chunks > 0 AND processed_chunks < total_chunks).
      $query = $this->database->select('search_api_item', 'sai')
        ->condition('index_id', $index->id())
        ->condition('total_chunks', 0, '>')
        ->condition('processed_chunks', 0, '>')
        ->where('sai.processed_chunks < sai.total_chunks');
      $query->addExpression(
        'SUM(sai.processed_chunks / sai.total_chunks)',
        'total_partial_progress'
      );
      $partial_progress_sum = $query->execute()->fetchField();

      // The sum will be null if no rows match, so cast to a float.
      $total_progress = $fully_completed_count + (float) $partial_progress_sum;

      // Update context with our new fractional progress.
      $context['results']['indexed'] = $total_progress;
      $context['message'] = $this->t(
        'Processed @percentage of items on @index (@progress / @total items).',
        [
          '@index' => $index->label(),
          '@percentage' => number_format(($total_progress / $original_count) * 100, 1) . '%',
          '@progress' => floor($total_progress),
          '@total' => $original_count,
        ]
      );

      // Check for completion.
      if ($current_remaining_item_count == 0) {
        $context['finished'] = 1;
        // Clean up the results for the 'finish' callback.
        $context['results']['indexed'] = $original_count;
        $context['results']['not indexed'] = 0;
      }
      else {
        // Set fractional progress for the batch API.
        $context['finished'] = ($total_progress / $original_count);
      }
    }
    catch (\Exception $e) {
      // Log exception to watchdog and abort the batch job.
      Error::logException($this->logger, $e);
      $context['message'] = $this->t(
        'An error occurred during indexing on @index: @message',
        [
          '@index' => $index->label(),
          '@message' => $e->getMessage(),
        ]
      );
      $context['finished'] = 1;
      $context['results']['not indexed'] = $context['sandbox']['original_item_count']
        - $context['results']['indexed'];
    }
  }

}
