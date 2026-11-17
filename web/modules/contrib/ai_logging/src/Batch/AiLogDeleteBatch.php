<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Batch;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Deletes AI logs in a batch.
 *
 * A site that logs every call can hold far more logs than fit in memory at
 * once, so they are deleted a few at a time.
 *
 * @internal
 */
class AiLogDeleteBatch {

  /**
   * Logs deleted per batch request.
   */
  public const LIMIT = 50;

  /**
   * Builds the batch.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title shown while the batch runs.
   * @param string[]|null $tags
   *   Only delete logs carrying one of these request tags, or NULL to delete
   *   every log.
   *
   * @return array
   *   The batch definition, for batch_set().
   */
  public static function build(TranslatableMarkup $title, ?array $tags = NULL): array {
    return (new BatchBuilder())
      ->setTitle($title)
      ->addOperation([static::class, 'deleteLogs'], [$tags])
      ->setFinishCallback([static::class, 'finished'])
      ->toArray();
  }

  /**
   * Batch operation: deletes the next set of logs.
   *
   * Only the logs that existed when the batch started are deleted, so calls
   * that are logged while it runs are kept and the batch always ends.
   *
   * @param string[]|null $tags
   *   Only delete logs carrying one of these request tags, or NULL to delete
   *   every log.
   * @param array|\ArrayAccess $context
   *   The batch context.
   */
  public static function deleteLogs(?array $tags, array|\ArrayAccess &$context): void {
    $storage = \Drupal::entityTypeManager()->getStorage('ai_log');

    if (!isset($context['sandbox']['total'])) {
      $max_id = static::query($tags)
        ->sort('id', 'DESC')
        ->range(0, 1)
        ->execute();
      $context['sandbox']['max_id'] = (int) reset($max_id);
      $context['sandbox']['total'] = (int) static::query($tags)->count()->execute();
      $context['sandbox']['deleted'] = 0;
      $context['results']['deleted'] = 0;
    }

    $ids = [];
    if ($context['sandbox']['total'] > 0) {
      $ids = static::query($tags)
        ->condition('id', $context['sandbox']['max_id'], '<=')
        ->sort('id')
        ->range(0, static::LIMIT)
        ->execute();
    }
    if ($ids) {
      $storage->delete($storage->loadMultiple($ids));
      $context['sandbox']['deleted'] += count($ids);
      $context['results']['deleted'] += count($ids);
    }

    $context['finished'] = ($ids === [] || $context['sandbox']['deleted'] >= $context['sandbox']['total'])
      ? 1
      : $context['sandbox']['deleted'] / $context['sandbox']['total'];
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed without errors.
   * @param array $results
   *   The results collected by the operation.
   */
  public static function finished(bool $success, array $results): void {
    $messenger = \Drupal::messenger();
    if (!$success) {
      $messenger->addError(new TranslatableMarkup('An error occurred while deleting the AI logs.'));
      return;
    }
    $messenger->addStatus(\Drupal::translation()->formatPlural(
      $results['deleted'] ?? 0,
      'Deleted 1 AI log.',
      'Deleted @count AI logs.',
    ));
  }

  /**
   * Builds a query for the logs to delete.
   *
   * @param string[]|null $tags
   *   Only match logs carrying one of these request tags, or NULL to match
   *   every log.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query.
   */
  protected static function query(?array $tags): QueryInterface {
    $query = \Drupal::entityTypeManager()->getStorage('ai_log')->getQuery()
      ->accessCheck(FALSE);
    if ($tags !== NULL) {
      $query->condition('tags', $tags, 'IN');
    }
    return $query;
  }

}
