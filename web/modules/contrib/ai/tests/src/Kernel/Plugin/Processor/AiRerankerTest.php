<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\Processor;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Plugin\search_api\processor\AiReranker;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValue;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSet;
use Drupal\search_api\Utility\Utility;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Tests the AiReranker search_api processor.
 *
 * Verifies that the processor reorders result items by the scores returned by
 * the AI reranking provider, extracts fulltext values, handles error branches
 * gracefully and never corrupts the result count.
 *
 * @coversDefaultClass \Drupal\ai\Plugin\search_api\processor\AiReranker
 * @group ai
 */
#[RunTestsInSeparateProcesses]
class AiRerankerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'key',
    'file',
    'field',
    'ai',
    'ai_test',
    // Provides the 'not_setup_provider' provider, which supports only 'chat'
    // and is used to exercise the "provider without rerank" branch.
    'not_setup_provider',
    'search_api',
  ];

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderPluginManager;

  /**
   * A logger that captures every record logged during a test.
   *
   * @var \Psr\Log\AbstractLogger
   */
  protected $capturingLogger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['ai', 'ai_test', 'system']);
    $this->installEntitySchema('ai_mock_provider_result');

    $this->aiProviderPluginManager = $this->container->get('ai.provider');

    // Register a logger that captures messages so tests can assert on them.
    $this->capturingLogger = new class() extends AbstractLogger {
      /**
       * The captured log records.
       *
       * @var array<int, array{level: mixed, message: string}>
       */
      public array $records = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->records[] = ['level' => $level, 'message' => (string) $message];
      }

    };
    $this->container->get('logger.factory')->addLogger($this->capturingLogger);
  }

  /**
   * Builds an AiReranker processor instance with the given configuration.
   *
   * @param array $configuration
   *   Plugin configuration overrides.
   *
   * @return \Drupal\ai\Plugin\search_api\processor\AiReranker
   *   A configured processor instance.
   */
  protected function buildProcessor(array $configuration = []): AiReranker {
    $defaults = [
      'provider_id' => 'echoai',
      'model_id' => 'gpt-test',
      'top_n' => 0,
      'source_fields' => [],
    ];
    $config = array_merge($defaults, $configuration);

    return AiReranker::create(
      $this->container,
      $config,
      'ai_reranker',
      [],
    );
  }

  /**
   * Returns TRUE if any captured log message contains the given needle.
   *
   * @param string $needle
   *   The substring to look for.
   *
   * @return bool
   *   Whether a matching message was logged.
   */
  protected function loggedMessageContains(string $needle): bool {
    foreach ($this->capturingLogger->records as $record) {
      if (str_contains($record['message'], $needle)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds a mock search_api QueryInterface returning the given keys.
   *
   * @param string|array $keys
   *   The search keys to return from getKeys().
   *
   * @return \Drupal\search_api\Query\QueryInterface&\PHPUnit\Framework\MockObject\MockObject
   *   The mock query.
   */
  protected function buildQuery(string|array $keys): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('getKeys')->willReturn($keys);
    return $query;
  }

  /**
   * Builds a ResultSet containing items with the given IDs.
   *
   * Each item has a single 'body' field whose value equals the item ID, making
   * it easy to assert ordering.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query to attach to the result set.
   * @param string[] $item_ids
   *   Ordered list of item IDs to create.
   * @param bool $use_text_value
   *   When TRUE, populate the field with a fulltext TextValue object instead
   *   of a plain string, mirroring what Search API stores for text fields.
   * @param bool $with_document_text
   *   When TRUE, attach declared document text via
   *   AiReranker::DOCUMENT_TEXT_EXTRA_DATA_KEY on every item.
   *
   * @return \Drupal\search_api\Query\ResultSet
   *   The populated result set.
   */
  protected function buildResultSet(QueryInterface $query, array $item_ids, bool $use_text_value = FALSE, bool $with_document_text = FALSE): ResultSet {
    $index = $this->createMock(IndexInterface::class);

    $items = [];
    foreach ($item_ids as $raw_id) {
      $combined_id = Utility::createCombinedId('entity:node', $raw_id . ':en');
      $item = new Item($index, $combined_id);

      $field = new Field($index, 'body');
      $field->setType('text');
      $field->addValue($use_text_value ? new TextValue($raw_id) : $raw_id);
      $item->setField('body', $field);
      $item->setFieldsExtracted(TRUE);
      if ($with_document_text) {
        $item->setExtraData(AiReranker::DOCUMENT_TEXT_EXTRA_DATA_KEY, 'declared document text for ' . $raw_id);
      }

      $items[$combined_id] = $item;
    }

    $results = new ResultSet($query);
    $results->setResultItems($items);
    $results->setResultCount(count($items));
    return $results;
  }

  /**
   * Tests that the processor plugin is discoverable via the plugin manager.
   */
  public function testProcessorIsDiscoverable(): void {
    $processor_manager = $this->container->get('plugin.manager.search_api.processor');
    $definitions = $processor_manager->getDefinitions();

    $this->assertArrayHasKey('ai_reranker', $definitions);
    $this->assertEquals('AI Reranker', (string) $definitions['ai_reranker']['label']);
  }

  /**
   * Tests that supportsIndex() is TRUE when a rerank provider is available.
   */
  public function testSupportsIndexWithRerankProvider(): void {
    // The ai_test EchoProvider supports the rerank operation.
    $index = $this->createMock(IndexInterface::class);
    $this->assertTrue(AiReranker::supportsIndex($index));
  }

  /**
   * Tests that configuration values round-trip through defaultConfiguration().
   */
  public function testDefaultConfiguration(): void {
    $processor = $this->buildProcessor();
    $config = $processor->getConfiguration();

    $this->assertSame('echoai', $config['provider_id']);
    $this->assertSame('gpt-test', $config['model_id']);
    $this->assertSame(0, $config['top_n']);
    $this->assertSame([], $config['source_fields']);
  }

  /**
   * Tests that submitConfigurationForm() splits and stores the selection.
   */
  public function testSubmitConfigurationFormRoundTrip(): void {
    $processor = $this->buildProcessor([
      'provider_id' => '',
      'model_id' => '',
      'source_fields' => [],
    ]);

    $form = [];
    $form_state = new FormState();
    $form_state->setValue('provider_model', 'echoai__gpt-awesome');
    $form_state->setValue('top_n', '5');
    $form_state->setValue('source_fields', ['title' => 'title', 'body' => 'body', 'field_x' => 0]);

    $processor->submitConfigurationForm($form, $form_state);
    $config = $processor->getConfiguration();

    $this->assertSame('echoai', $config['provider_id']);
    $this->assertSame('gpt-awesome', $config['model_id']);
    $this->assertSame(5, $config['top_n']);
    $this->assertSame(['title', 'body'], $config['source_fields']);
  }

  /**
   * Tests that results are reordered by the reranker's scores.
   *
   * EchoProvider assigns ascending scores so the LAST input document scores
   * highest. When we pass items in order [item-a, item-b, item-c]:
   *   index 0 (item-a) -> score 0.33
   *   index 1 (item-b) -> score 0.67
   *   index 2 (item-c) -> score 1.00
   *
   * After sorting descending the reranker output order is:
   *   [index=2, index=1, index=0]
   *
   * The processor must reorder the result set to match: item-c, item-b, item-a.
   */
  public function testPostprocessReordersResults(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    $query = $this->buildQuery('capital of France');

    $results = $this->buildResultSet($query, ['item-a', 'item-b', 'item-c']);
    $processor->postprocessSearchResults($results);

    $reordered = array_values($results->getResultItems());
    $this->assertCount(3, $reordered);

    $ids = array_map(fn($item) => $item->getId(), $reordered);
    $expected_ids = [
      Utility::createCombinedId('entity:node', 'item-c:en'),
      Utility::createCombinedId('entity:node', 'item-b:en'),
      Utility::createCombinedId('entity:node', 'item-a:en'),
    ];
    $this->assertSame($expected_ids, $ids);

    // The relevance scores returned by the provider must be applied.
    $this->assertSame(1.0, $reordered[0]->getScore());
  }

  /**
   * Tests that fulltext TextValue field values are extracted, not skipped.
   *
   * This is a regression guard for the bug where only is_string() values were
   * accepted, causing every text field to fall back and log a warning.
   */
  public function testPostprocessExtractsTextValueFields(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    $query = $this->buildQuery('capital of France');

    // Populate the body field with fulltext TextValue objects.
    $results = $this->buildResultSet($query, ['item-a', 'item-b', 'item-c'], TRUE);
    $processor->postprocessSearchResults($results);

    // Reordering must still work (proves the documents were built).
    $ids = array_map(fn($item) => $item->getId(), array_values($results->getResultItems()));
    $this->assertSame(
      Utility::createCombinedId('entity:node', 'item-c:en'),
      $ids[0],
    );

    // No "no text" warning must be logged when extraction succeeds.
    $this->assertFalse(
      $this->loggedMessageContains('had no text in the configured source fields'),
      'Extraction of TextValue fields must not log the missing-text warning.',
    );
  }

  /**
   * Builds a single item with a body field and optional declared document text.
   *
   * @param string|null $declared_document_text
   *   Text for AiReranker::DOCUMENT_TEXT_EXTRA_DATA_KEY, or NULL to omit it.
   * @param string|null $generic_content
   *   Optional "content" extra data (backend generic key that must be ignored).
   *
   * @return \Drupal\search_api\Item\Item
   *   The item, whose body field reads 'full entity text, not the chunk'.
   */
  protected function buildItemWithSourceAndExtraData(?string $declared_document_text = NULL, ?string $generic_content = NULL): Item {
    $index = $this->createMock(IndexInterface::class);
    $item = new Item($index, Utility::createCombinedId('entity:node', '1:en'));

    $field = new Field($index, 'body');
    $field->setType('text');
    $field->addValue('full entity text, not the chunk');
    $item->setField('body', $field);
    $item->setFieldsExtracted(TRUE);
    if ($declared_document_text !== NULL) {
      $item->setExtraData(AiReranker::DOCUMENT_TEXT_EXTRA_DATA_KEY, $declared_document_text);
    }
    if ($generic_content !== NULL) {
      $item->setExtraData('content', $generic_content);
    }

    return $item;
  }

  /**
   * Tests that declared document text takes precedence over source fields.
   *
   * Without this, chunk-level reranking degrades to entity-level reranking.
   */
  public function testExtractItemTextPrefersDeclaredDocumentTextOverSourceFields(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    $item = $this->buildItemWithSourceAndExtraData('chunk-specific text');

    $method = new \ReflectionMethod(AiReranker::class, 'extractItemText');

    $this->assertSame('chunk-specific text', $method->invoke($processor, $item));
  }

  /**
   * Tests that generic "content" extra data is never treated as document text.
   *
   * Backends may attach "content" in both chunk and entity modes; only the
   * declared DOCUMENT_TEXT_EXTRA_DATA_KEY contract is authoritative.
   */
  public function testExtractItemTextIgnoresGenericContentExtraData(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    $item = $this->buildItemWithSourceAndExtraData(NULL, 'one arbitrary chunk of the entity');

    $method = new \ReflectionMethod(AiReranker::class, 'extractItemText');

    $this->assertSame('full entity text, not the chunk', $method->invoke($processor, $item));
  }

  /**
   * Tests that source fields are used when no declared document text is set.
   */
  public function testExtractItemTextFallsBackToSourceFieldsWithoutDeclaredDocumentText(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    $item = $this->buildItemWithSourceAndExtraData();

    $method = new \ReflectionMethod(AiReranker::class, 'extractItemText');

    $this->assertSame('full entity text, not the chunk', $method->invoke($processor, $item));
  }

  /**
   * Tests that postprocess uses declared document text when present.
   *
   * No source fields are configured, so only the declared extra data can
   * supply text and the absence of the warning proves it was used.
   */
  public function testPostprocessUsesDeclaredDocumentText(): void {
    $processor = $this->buildProcessor(['source_fields' => []]);
    $query = $this->buildQuery('capital of France');
    $results = $this->buildResultSet($query, ['a', 'b', 'c'], FALSE, TRUE);

    $processor->postprocessSearchResults($results);

    $this->assertFalse(
      $this->loggedMessageContains('had no text in the configured source fields'),
      'Items must supply document text from AiReranker::DOCUMENT_TEXT_EXTRA_DATA_KEY.',
    );
  }

  /**
   * Tests that postprocess ignores generic "content" extra data.
   *
   * Items carry "content" but not the declared key, so the missing-text
   * warning proves the generic key was not silently substituted.
   */
  public function testPostprocessIgnoresGenericContentExtraData(): void {
    $processor = $this->buildProcessor(['source_fields' => []]);
    $query = $this->buildQuery('capital of France');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    foreach ($results->getResultItems() as $item) {
      $item->setExtraData('content', 'generic content that must be ignored');
    }

    $processor->postprocessSearchResults($results);

    $this->assertTrue(
      $this->loggedMessageContains('had no text in the configured source fields'),
      'Generic "content" extra data must not be used as document text.',
    );
  }

  /**
   * Tests that top_n does not truncate results or change the result count.
   *
   * The postprocess_query stage runs per page, so truncating or rewriting the
   * count would break the pager. top_n is only a hint passed to the provider.
   */
  public function testTopnDoesNotTruncateOrChangeCount(): void {
    $processor = $this->buildProcessor(['top_n' => 2, 'source_fields' => ['body']]);
    $query = $this->buildQuery('some query');
    $results = $this->buildResultSet($query, ['a', 'b', 'c', 'd']);
    // Simulate a query-wide total larger than this page.
    $results->setResultCount(50);

    $processor->postprocessSearchResults($results);

    // All items on the page are preserved and the total count is untouched.
    $this->assertCount(4, $results->getResultItems());
    $this->assertSame(50, $results->getResultCount());
  }

  /**
   * Tests that top_n = 0 leaves the result count untouched.
   */
  public function testTopnZeroPreservesResultCount(): void {
    $processor = $this->buildProcessor(['top_n' => 0, 'source_fields' => ['body']]);
    $query = $this->buildQuery('some query');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $results->setResultCount(42);

    $processor->postprocessSearchResults($results);

    $this->assertCount(3, $results->getResultItems());
    $this->assertSame(42, $results->getResultCount());
  }

  /**
   * Tests that items with no source text log a single warning, never the ID.
   */
  public function testMissingSourceTextWarnsOnceWithoutIds(): void {
    // No source fields selected -> every item has empty document text.
    $processor = $this->buildProcessor(['source_fields' => []]);
    $query = $this->buildQuery('a query');
    $results = $this->buildResultSet($query, ['secret-a', 'secret-b']);

    $processor->postprocessSearchResults($results);

    $warnings = array_filter(
      $this->capturingLogger->records,
      fn($r) => str_contains($r['message'], 'had no text in the configured source fields'),
    );
    // Exactly one aggregate warning, not one per item.
    $this->assertCount(1, $warnings);
    // Internal item IDs must never appear in any log message.
    $this->assertFalse($this->loggedMessageContains('secret-a'));
    $this->assertFalse($this->loggedMessageContains('secret-b'));
  }

  /**
   * Tests that empty keys cause the processor to skip reranking.
   */
  public function testEmptyKeysSkipsReranking(): void {
    $processor = $this->buildProcessor();
    $query = $this->buildQuery('');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $original_items = $results->getResultItems();

    $processor->postprocessSearchResults($results);

    $this->assertSame(
      array_keys($original_items),
      array_keys($results->getResultItems()),
    );
    $this->assertCount(3, $results->getResultItems());
  }

  /**
   * Tests that a fully-negated keys array is treated as an empty query.
   */
  public function testNegatedKeysAreSkipped(): void {
    $processor = $this->buildProcessor(['source_fields' => ['body']]);
    // "-apple": a negated-only query should not be sent to the reranker.
    $keys = [
      '#conjunction' => 'AND',
      '#negation' => TRUE,
      0 => 'apple',
    ];
    $query = $this->buildQuery($keys);
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $original_keys = array_keys($results->getResultItems());

    $processor->postprocessSearchResults($results);

    // Order unchanged because the flattened query string is empty.
    $this->assertSame($original_keys, array_keys($results->getResultItems()));
  }

  /**
   * Tests flattenKeys() directly for nested arrays, meta keys and negation.
   */
  public function testFlattenKeys(): void {
    $processor = $this->buildProcessor();
    $method = new \ReflectionMethod(AiReranker::class, 'flattenKeys');

    // Plain string.
    $this->assertSame('hello world', $method->invoke($processor, ' hello world '));

    // Nested array with a negated sub-tree and meta keys.
    $keys = [
      '#conjunction' => 'AND',
      0 => 'apple',
      1 => [
        '#conjunction' => 'OR',
        '#negation' => TRUE,
        0 => 'banana',
        1 => 'cherry',
      ],
      2 => 'pear',
    ];
    $this->assertSame('apple pear', $method->invoke($processor, $keys));
  }

  /**
   * Tests that null / empty result set does not cause errors.
   */
  public function testEmptyResultSetSkipsReranking(): void {
    $processor = $this->buildProcessor();
    $query = $this->buildQuery('test query');

    $results = new ResultSet($query);
    $results->setResultItems([]);
    $results->setResultCount(0);

    $processor->postprocessSearchResults($results);

    $this->assertCount(0, $results->getResultItems());
  }

  /**
   * Tests that missing provider_id causes the processor to skip reranking.
   */
  public function testMissingProviderSkipsReranking(): void {
    $processor = $this->buildProcessor(['provider_id' => '', 'model_id' => '']);
    $query = $this->buildQuery('test');
    $results = $this->buildResultSet($query, ['a', 'b']);
    $original_keys = array_keys($results->getResultItems());

    $processor->postprocessSearchResults($results);

    $this->assertSame($original_keys, array_keys($results->getResultItems()));
  }

  /**
   * Tests that a provider lacking rerank support is skipped, with a warning.
   *
   * Uses the real 'not_setup_provider' provider, which supports only 'chat'.
   */
  public function testProviderWithoutRerankIsSkipped(): void {
    $processor = $this->buildProcessor([
      'provider_id' => 'not_setup_provider',
      'model_id' => 'gpt-test',
      'source_fields' => ['body'],
    ]);

    $query = $this->buildQuery('test');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $original_keys = array_keys($results->getResultItems());

    $processor->postprocessSearchResults($results);

    $this->assertSame($original_keys, array_keys($results->getResultItems()));
    $this->assertTrue($this->loggedMessageContains('does not support the rerank operation'));
  }

  /**
   * Tests that a failure during reranking is caught and logged.
   *
   * A non-existent provider id makes createInstance() throw, exercising the
   * catch (\Throwable) branch.
   */
  public function testProviderFailureIsHandled(): void {
    $processor = $this->buildProcessor([
      'provider_id' => 'this_provider_does_not_exist',
      'model_id' => 'gpt-test',
      'source_fields' => ['body'],
    ]);

    $query = $this->buildQuery('test');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $original_keys = array_keys($results->getResultItems());

    // Must not throw; order preserved and failure logged.
    $processor->postprocessSearchResults($results);

    $this->assertSame($original_keys, array_keys($results->getResultItems()));
    $this->assertTrue($this->loggedMessageContains('AI Reranker failed'));
  }

  /**
   * Tests that an unusable index response preserves order with a warning.
   *
   * The EchoProvider returns a response with no "index" keys for the sentinel
   * model id 'unusable-rerank'.
   */
  public function testUnusableRerankResponsePreservesOrder(): void {
    $processor = $this->buildProcessor([
      'model_id' => 'unusable-rerank',
      'source_fields' => ['body'],
    ]);

    $query = $this->buildQuery('test');
    $results = $this->buildResultSet($query, ['a', 'b', 'c']);
    $original_keys = array_keys($results->getResultItems());

    $processor->postprocessSearchResults($results);

    $this->assertSame($original_keys, array_keys($results->getResultItems()));
    $this->assertTrue($this->loggedMessageContains('no usable index values'));
  }

}
