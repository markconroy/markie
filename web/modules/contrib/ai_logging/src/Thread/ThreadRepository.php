<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Query\TableSortExtender;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Groups AI logs into conversation threads.
 *
 * A log belongs to a thread when one of its request tags starts with one of
 * the configured thread tag prefixes. The rest of the tag is the thread ID.
 * Logs are grouped by the thread ID rather than by the whole tag, so a
 * conversation that is tagged with several prefixes for the same ID (such as
 * an AI Assistant that runs an agent) is shown as one thread.
 */
class ThreadRepository {

  /**
   * Constructs a ThreadRepository object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected Connection $database,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Gets the configured thread tag prefixes.
   *
   * @return string[]
   *   The prefixes, longest first so that a prefix which starts with another
   *   prefix takes precedence.
   */
  public function getTagPrefixes(): array {
    $prefixes = $this->configFactory->get('ai_logging.settings')->get('thread_tag_prefixes') ?? [];
    $prefixes = array_values(array_unique(array_filter(array_map('trim', $prefixes))));
    usort($prefixes, static fn(string $first, string $second): int => mb_strlen($second) <=> mb_strlen($first));
    return $prefixes;
  }

  /**
   * Checks whether any thread tag prefix is configured.
   *
   * @return bool
   *   TRUE if threads can be listed.
   */
  public function isEnabled(): bool {
    return $this->getTagPrefixes() !== [];
  }

  /**
   * Gets the thread ID from a request tag.
   *
   * @param string $tag
   *   The request tag.
   *
   * @return string|null
   *   The thread ID, or NULL if the tag does not identify a thread.
   */
  public function getThreadIdFromTag(string $tag): ?string {
    foreach ($this->getTagPrefixes() as $prefix) {
      if (str_starts_with($tag, $prefix) && $tag !== $prefix) {
        return substr($tag, strlen($prefix));
      }
    }
    return NULL;
  }

  /**
   * Gets every request tag that identifies a thread ID.
   *
   * @param string $thread_id
   *   The thread ID.
   *
   * @return string[]
   *   One tag per configured prefix.
   */
  public function getTagsForThreadId(string $thread_id): array {
    return array_map(static fn(string $prefix): string => $prefix . $thread_id, $this->getTagPrefixes());
  }

  /**
   * Lists conversation threads, sorted by a table header and paged.
   *
   * @param array $header
   *   A table header whose sortable columns use the fields thread_id,
   *   started, last_activity, calls or tokens.
   * @param int $limit
   *   The number of threads per page.
   *
   * @return object[]
   *   One row per thread with the properties thread_id, started,
   *   last_activity, calls and tokens (NULL if no call reported tokens).
   */
  public function listThreads(array $header, int $limit): array {
    $query = $this->buildThreadsQuery();
    if (!$query) {
      return [];
    }
    return $query
      ->extend(TableSortExtender::class)
      ->orderByHeader($header)
      ->extend(PagerSelectExtender::class)
      ->limit($limit)
      ->orderBy('thread_id')
      ->execute()
      ->fetchAll();
  }

  /**
   * Loads the logs of one thread, oldest first.
   *
   * @param string $thread_id
   *   The thread ID.
   *
   * @return \Drupal\ai_logging\AiLogInterface[]
   *   The logs, in the order they were created. Logs created in the same
   *   second are ordered by ID.
   */
  public function loadThreadEntries(string $thread_id): array {
    $tags = $this->getTagsForThreadId($thread_id);
    if ($thread_id === '' || !$tags) {
      return [];
    }
    $storage = $this->entityTypeManager->getStorage('ai_log');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('tags', $tags, 'IN')
      ->sort('created')
      ->sort('id')
      ->execute();
    if (!$ids) {
      return [];
    }
    $logs = $storage->loadMultiple($ids);
    // Iterate $ids to keep the query order.
    $entries = [];
    foreach ($ids as $id) {
      if (isset($logs[$id])) {
        $entries[] = $logs[$id];
      }
    }
    return $entries;
  }

  /**
   * Loads the prompt of the earliest chat call of a thread.
   *
   * @param string $thread_id
   *   The thread ID.
   * @param string $required_tag_pattern
   *   If not empty, only a call with a tag matching this SQL LIKE pattern
   *   counts (e.g. "ai_agents_prompt_%").
   *
   * @return string|null
   *   The prompt, or NULL if there is no such call.
   */
  public function loadFirstChatPrompt(string $thread_id, string $required_tag_pattern = ''): ?string {
    $tags = $this->getTagsForThreadId($thread_id);
    if ($thread_id === '' || !$tags) {
      return NULL;
    }
    $query = $this->database->select('ai_log', 'l');
    $query->join('ai_log__tags', 't', 't.entity_id = l.id');
    $query->fields('l', ['prompt']);
    $query->condition('t.tags_value', $tags, 'IN');
    $query->condition('l.operation_type', 'chat');
    if ($required_tag_pattern !== '') {
      $query->join('ai_log__tags', 'required_tag', 'required_tag.entity_id = l.id');
      $query->condition('required_tag.tags_value', $required_tag_pattern, 'LIKE');
    }
    $query->orderBy('l.created');
    $query->orderBy('l.id');
    $query->range(0, 1);
    $prompt = $query->execute()->fetchField();
    return $prompt === FALSE ? NULL : (string) $prompt;
  }

  /**
   * Builds the query that groups logs by thread ID.
   *
   * @return \Drupal\Core\Database\Query\SelectInterface|null
   *   The query, or NULL if no thread tag prefix is configured.
   */
  protected function buildThreadsQuery(): ?SelectInterface {
    $prefixes = $this->getTagPrefixes();
    if (!$prefixes) {
      return NULL;
    }

    // One row per log and thread ID. DISTINCT stops a log that carries the
    // same thread ID under two prefixes from being counted twice.
    $thread_tags = $this->database->select('ai_log__tags', 't');
    $thread_tags->addField('t', 'entity_id');
    $prefix_conditions = $thread_tags->orConditionGroup();
    $thread_id_expression = 'CASE';
    $arguments = [];
    foreach ($prefixes as $prefix_index => $prefix) {
      $prefix_conditions->condition('t.tags_value', $this->database->escapeLike($prefix) . '_%', 'LIKE');
      $prefix_length = mb_strlen($prefix);
      $thread_id_expression .= ' WHEN SUBSTR(t.tags_value, 1, ' . $prefix_length . ') = :thread_prefix_' . $prefix_index
        . ' THEN SUBSTR(t.tags_value, ' . ($prefix_length + 1) . ')';
      $arguments[':thread_prefix_' . $prefix_index] = $prefix;
    }
    $thread_id_expression .= ' END';
    $thread_tags->addExpression($thread_id_expression, 'thread_id', $arguments);
    $thread_tags->condition($prefix_conditions);
    $thread_tags->distinct();

    $query = $this->database->select($thread_tags, 'thread_tags');
    $query->join('ai_log', 'l', 'l.id = thread_tags.entity_id');
    $query->addField('thread_tags', 'thread_id');
    // Some databases match LIKE without regard to case, but compare the
    // prefix with case, which leaves no thread ID for such a tag.
    $query->isNotNull('thread_tags.thread_id');
    $query->addExpression('MIN(l.created)', 'started');
    $query->addExpression('MAX(l.created)', 'last_activity');
    $query->addExpression('COUNT(l.id)', 'calls');
    $query->addExpression('SUM(l.tokens_total)', 'tokens');
    $query->groupBy('thread_tags.thread_id');
    return $query;
  }

}
