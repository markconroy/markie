<?php

namespace Drupal\ai_search\Plugin\search_api\tracker;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiTracker;
use Drupal\search_api\Plugin\search_api\tracker\Basic;

/**
 * Provides a tracker for chunked content processing.
 *
 * This tracker extends the default functionality to manage items that are
 * indexed in multiple batches (chunks). The custom logic is only applied when
 * the index is configured with the 'search_api_ai_search' backend. Otherwise,
 * it falls back to the parent 'Basic' tracker's behavior.
 */
#[SearchApiTracker(
  id: 'ai_search_tracker',
  label: new TranslatableMarkup('AI Search Chunked Tracker'),
  description: new TranslatableMarkup('Tracks items that are indexed in multiple chunks. Use for AI search indexing.')
)]
class AiSearchTracker extends Basic {

  /**
   * Checks if the current index uses the AI Search backend.
   *
   * @return bool
   *   True if the AI Search backend is active.
   */
  protected function isAiSearchBackend(): bool {
    $backend_id = $this->getIndex()?->getServerInstance()?->getBackend()?->getPluginId();
    return $backend_id === 'search_api_ai_search';
  }

  /**
   * {@inheritdoc}
   */
  public function trackItemsUpdated(?array $ids = NULL): void {
    if (!$this->isAiSearchBackend()) {
      parent::trackItemsUpdated($ids);
      return;
    }

    if ($ids === NULL) {
      $this->trackAllItemsUpdated();
      return;
    }

    try {
      // For specific items, mark them as not indexed and reset all progress.
      // The total_chunks will be recalculated by the indexer on the next run.
      $this->createUpdateStatement()
        ->condition('item_id', $ids, 'IN')
        ->fields([
          'changed' => $this->getTimeService()->getRequestTime(),
          'status' => self::STATUS_NOT_INDEXED,
          'total_chunks' => NULL,
          'processed_chunks' => 0,
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->logException($e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function trackAllItemsUpdated($datasource_id = NULL): void {
    if (!$this->isAiSearchBackend()) {
      parent::trackAllItemsUpdated($datasource_id);
      return;
    }

    try {
      $update = $this->createUpdateStatement();
      $update->fields([
        'changed' => $this->getTimeService()->getRequestTime(),
        'status' => self::STATUS_NOT_INDEXED,
        'total_chunks' => NULL,
        'processed_chunks' => 0,
      ]);
      if ($datasource_id) {
        $update->condition('datasource', $datasource_id);
      }
      $update->execute();
    }
    catch (\Exception $e) {
      $this->logException($e);
    }
  }

}
