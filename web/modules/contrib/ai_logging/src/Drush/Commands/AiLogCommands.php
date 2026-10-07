<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\ai_logging\Thread\ThreadRepository;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for inspecting AI LLM call logs.
 */
final class AiLogCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs an AiLogCommands object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai_logging\Thread\ThreadRepository $threadRepository
   *   The thread repository.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ThreadRepository $threadRepository,
  ) {
  }

  /**
   * Show recent AI LLM call logs.
   *
   * Queries ai_log entities to display prompts, responses, and metadata
   * from LLM provider calls. Useful for debugging AI agents, chatbot
   * interactions, or any AI-powered feature.
   */
  #[CLI\Command(name: 'ai:logs', aliases: ['ail', 'ai-logs'])]
  #[CLI\Option(name: 'count', description: 'Number of recent log entries to show.')]
  #[CLI\Option(name: 'tag', description: 'Filter by tag (e.g. ai_agents, ai_chatbot).')]
  #[CLI\Option(name: 'provider', description: 'Filter by provider (e.g. anthropic, openai).')]
  #[CLI\Option(name: 'id', description: 'Show a single log entry with full details. Use --format=yaml for readable output.')]
  #[CLI\FieldLabels(labels: [
    'id' => 'ID',
    'date' => 'Date',
    'operation_type' => 'Type',
    'provider' => 'Provider',
    'model' => 'Model',
    'tags' => 'Tags',
    'prompt' => 'Prompt',
    'output_text' => 'Response',
    'response_text' => 'Reply',
    'tokens_total' => 'Tokens',
    'extra_data' => 'Extra Data',
    'configuration' => 'Configuration',
  ])]
  #[CLI\DefaultTableFields(fields: [
    'id',
    'date',
    'operation_type',
    'provider',
    'model',
    'tags',
    'prompt',
    'output_text',
    'extra_data',
    'configuration',
  ])]
  #[CLI\FilterDefaultField(field: 'tags')]
  #[CLI\Usage(name: 'drush ai:logs', description: 'Show the 10 most recent AI calls.')]
  #[CLI\Usage(name: 'drush ai:logs --count=20', description: 'Show the last 20 AI calls.')]
  #[CLI\Usage(name: 'drush ai:logs --tag=ai_agents --count=5', description: 'Last 5 agent-related AI calls.')]
  #[CLI\Usage(name: 'drush ai:logs --provider=anthropic', description: 'Filter by provider.')]
  #[CLI\Usage(name: 'drush ai:logs --id=42 --format=yaml', description: 'Full details of log entry #42.')]
  #[CLI\Usage(name: 'drush ai:logs --format=json', description: 'Output as JSON for scripting.')]
  #[CLI\ValidateModulesEnabled(modules: ['ai_logging'])]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function logs(
    array $options = [
      'count' => 10,
      'tag' => parent::REQ,
      'provider' => parent::REQ,
      'id' => parent::REQ,
      'format' => 'table',
    ],
  ): ?RowsOfFields {
    $storage = $this->entityTypeManager->getStorage('ai_log');

    // Single entry mode.
    if (!empty($options['id'])) {
      $log = $storage->load((int) $options['id']);
      if (!$log) {
        throw new \Exception(sprintf('AI log entry #%s not found.', $options['id']));
      }
      return new RowsOfFields([$this->buildRow($log, FALSE)]);
    }

    // List mode.
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, (int) ($options['count'] ?: 10));

    if (!empty($options['tag'])) {
      $query->condition('tags', $options['tag']);
    }
    if (!empty($options['provider'])) {
      $query->condition('provider', $options['provider']);
    }

    $ids = $query->execute();
    if (empty($ids)) {
      $this->logger()?->notice('No AI log entries found. Ensure logging is enabled at /admin/config/ai/logging/settings.');
      return NULL;
    }

    $logs = $storage->loadMultiple($ids);
    $rows = [];
    // Iterate $ids to preserve query sort order.
    foreach ($ids as $id) {
      if (isset($logs[$id])) {
        $rows[] = $this->buildRow($logs[$id], TRUE);
      }
    }

    return new RowsOfFields($rows);
  }

  /**
   * Show every AI LLM call of one conversation thread, oldest first.
   *
   * Calls belong to a thread when they carry a request tag starting with one
   * of the thread tag prefixes set on the AI Logging settings page, such as
   * "ai_agents_thread_". Pass the thread ID without its prefix.
   */
  #[CLI\Command(name: 'ai:logs:thread', aliases: ['ailt', 'ai-logging:thread'])]
  #[CLI\Argument(name: 'thread_id', description: 'The thread ID, without its tag prefix.')]
  #[CLI\FieldLabels(labels: [
    'id' => 'ID',
    'date' => 'Date',
    'operation_type' => 'Type',
    'provider' => 'Provider',
    'model' => 'Model',
    'tags' => 'Tags',
    'prompt' => 'Prompt',
    'output_text' => 'Response',
    'response_text' => 'Reply',
    'tokens_total' => 'Tokens',
    'extra_data' => 'Extra Data',
    'configuration' => 'Configuration',
  ])]
  #[CLI\DefaultTableFields(fields: [
    'id',
    'date',
    'operation_type',
    'model',
    'prompt',
    'response_text',
    'tokens_total',
  ])]
  #[CLI\Usage(name: 'drush ai:logs:thread abc123', description: 'Show the calls of thread abc123.')]
  #[CLI\Usage(name: 'drush ai:logs:thread abc123 --format=json', description: 'Full details of every call, as JSON.')]
  #[CLI\ValidateModulesEnabled(modules: ['ai_logging'])]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function thread(
    string $thread_id,
    array $options = [
      'format' => 'table',
    ],
  ): ?RowsOfFields {
    if (!$this->threadRepository->isEnabled()) {
      throw new \Exception('No thread tag prefixes are configured at /admin/config/ai/logging/settings.');
    }
    $entries = $this->threadRepository->loadThreadEntries($thread_id);
    if (!$entries) {
      $this->logger()?->notice(sprintf('No AI log entries found for thread %s.', $thread_id));
      return NULL;
    }
    // Only a table needs shortened text.
    $truncate = $options['format'] === 'table';
    $rows = [];
    foreach ($entries as $entry) {
      $rows[] = $this->buildRow($entry, $truncate);
    }
    return new RowsOfFields($rows);
  }

  /**
   * Builds a row array from an ai_log entity.
   *
   * @param \Drupal\ai_logging\AiLogInterface $log
   *   The log entity.
   * @param bool $truncate
   *   TRUE to truncate long text fields for list display.
   *
   * @return array
   *   Associative array keyed by field machine name.
   */
  protected function buildRow(object $log, bool $truncate): array {
    $tags = [];
    foreach ($log->get('tags') as $item) {
      $tags[] = $item->value;
    }

    $prompt = $log->get('prompt')->value ?? '';
    $outputText = $log->get('output_text')->value ?? '';
    $responseText = $log->get('response_text')->value ?? '';
    $created = $log->get('created')->value;

    return [
      'id' => $log->id(),
      'date' => $created ? date('Y-m-d H:i:s', (int) $created) : '',
      'operation_type' => $log->get('operation_type')->value ?? '',
      'provider' => $log->get('provider')->value ?? '',
      'model' => $log->get('model')->value ?? '',
      'tags' => implode(', ', $tags),
      'prompt' => $truncate ? Unicode::truncate($this->collapseWhitespace($prompt), 80, TRUE, TRUE) : $prompt,
      'output_text' => $truncate ? Unicode::truncate($this->collapseWhitespace($outputText), 80, TRUE, TRUE) : $outputText,
      'response_text' => $truncate ? Unicode::truncate($this->collapseWhitespace($responseText), 80, TRUE, TRUE) : $responseText,
      'tokens_total' => $log->get('tokens_total')->value,
      'extra_data' => $log->get('extra_data')->value ?? '',
      'configuration' => $log->get('configuration')->value ?? '',
    ];
  }

  /**
   * Collapses whitespace for preview display.
   *
   * @param string $text
   *   The text to normalize.
   *
   * @return string
   *   Text with all whitespace collapsed to single spaces.
   */
  protected function collapseWhitespace(string $text): string {
    return trim((string) preg_replace('/\s+/', ' ', $text));
  }

}
