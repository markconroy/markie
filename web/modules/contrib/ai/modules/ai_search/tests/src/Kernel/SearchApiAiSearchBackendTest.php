<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\Tests\UnitTestCase;
use Drupal\search_api\Query\QueryInterface;
use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend;

/**
 * Tests the isolated methods of the AI Search backend plugin.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend
 * @group ai_search
 */
class SearchApiAiSearchBackendTest extends UnitTestCase {

  /**
   * The backend instance to test.
   *
   * @var \Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend
   */
  protected $backend;

  /**
   * The reflected protected method.
   *
   * @var \ReflectionMethod
   */
  protected $getSearchVectorInputMethod;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $reflection = new \ReflectionClass(SearchApiAiSearchBackend::class);
    $this->backend = $reflection->newInstanceWithoutConstructor();
    $this->getSearchVectorInputMethod = $reflection->getMethod('getSearchVectorInput');
    $this->getSearchVectorInputMethod->setAccessible(TRUE);
  }

  /**
   * Builds a query mock that yields no vector input, forcing querySearch().
   */
  private function mockQueryWithoutVectorInput(): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('getOption')
      ->with('vector_input', FALSE)
      ->willReturn(FALSE);
    $query->method('getKeys')->willReturn(['#conjunction' => 'AND']);
    return $query;
  }

  /**
   * Injects a mocked VDB client directly, bypassing the provider manager.
   */
  private function setVdbClient(AiVdbProviderInterface $vdb_client): void {
    $property = new \ReflectionProperty(SearchApiAiSearchBackend::class, 'vdbClient');
    $property->setAccessible(TRUE);
    $property->setValue($this->backend, $vdb_client);
  }

  /**
   * Invokes the protected recursive search method.
   */
  private function invokeDoSearchWithIteration(
    QueryInterface $query,
    array $params,
    bool $bypass_access,
    array &$results,
    int $start_limit,
    int $start_offset,
    array $excluded_entity_ids = [],
  ): array {
    $method = new \ReflectionMethod(SearchApiAiSearchBackend::class, 'doSearchWithIteration');
    $method->setAccessible(TRUE);
    $args = [
      $query, $params, $bypass_access, FALSE, FALSE, &$results,
      $start_limit, $start_offset, 0, $excluded_entity_ids,
    ];
    return $method->invokeArgs($this->backend, $args);
  }

  /**
   * Tests the early return when vector_input is passed via params.
   *
   * @covers ::getSearchVectorInput
   */
  public function testVectorInputViaParamsBypassesGeneration(): void {
    $expected_vector = [0.1, 0.2, 0.3];
    $params = ['vector_input' => $expected_vector];

    $query = $this->createMock(QueryInterface::class);

    // Proves that it returns early before even checking the query options.
    $query->expects($this->never())->method('getOption');
    $query->expects($this->never())->method('getKeys');

    $result = $this->getSearchVectorInputMethod->invokeArgs($this->backend, [$query, $params]);
    $this->assertEquals($expected_vector, $result);
  }

  /**
   * Tests the early return when vector_input is passed via query options.
   *
   * @covers ::getSearchVectorInput
   */
  public function testVectorInputViaQueryOptionBypassesGeneration(): void {
    $expected_vector = [0.4, 0.5, 0.6];
    $params = [];

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getOption')
      ->with('vector_input', FALSE)
      ->willReturn($expected_vector);

    // Proves that it returns early without generating AI embeddings.
    $query->expects($this->never())->method('getKeys');

    $result = $this->getSearchVectorInputMethod->invokeArgs($this->backend, [$query, $params]);
    $this->assertEquals($expected_vector, $result);
  }

  /**
   * Tests returning empty array when no vector or keys are provided.
   *
   * @covers ::getSearchVectorInput
   */
  public function testVectorInputReturnsEmptyWhenNoKeysProvided(): void {
    $params = [];

    $query = $this->createMock(QueryInterface::class);
    $query->expects($this->once())
      ->method('getOption')
      ->with('vector_input', FALSE)
      ->willReturn(FALSE);

    // Return empty array (or just a conjunction) to trigger the empty fallback.
    $query->expects($this->once())
      ->method('getKeys')
      ->willReturn(['#conjunction' => 'AND']);

    // Because getKeys is empty after the conjunction is unset, it never reaches
    // the AI provider logic and returns an empty array.
    $result = $this->getSearchVectorInputMethod->invokeArgs($this->backend, [$query, $params]);
    $this->assertEquals([], $result, 'Should return an empty array when there are no actual search terms.');
  }

  /**
   * Tests that a bypass_access retry advances the offset without doubling.
   *
   * Regression test for #3584030: previously the offset was only advanced
   * in the !$bypass_access branch, so a bypass_access search that needed to
   * retry (e.g. to skip duplicate chunks of the same entity) kept re-fetching
   * offset 0 on every iteration.
   *
   * @covers ::doSearchWithIteration
   */
  public function testBypassAccessAdvancesOffsetWithoutDoublingBatchSize(): void {
    $start_limit = 2;
    $start_offset = 0;

    $captured_calls = [];
    $vdb_client = $this->createMock(AiVdbProviderInterface::class);
    $vdb_client->expects($this->exactly(2))
      ->method('querySearch')
      ->willReturnCallback(function (string $collection_name, array $output_fields, mixed $filters = '', int $limit = 10, int $offset = 0, string $database = 'default') use (&$captured_calls) {
        $captured_calls[] = ['limit' => $limit, 'offset' => $offset];
        if (count($captured_calls) === 1) {
          // Two chunks of the same entity: the second is a local duplicate,
          // so only one distinct result survives this iteration.
          return [
            [
              'id' => 'chunk-1',
              'drupal_entity_id' => 'entity:node/1:en',
              'drupal_long_id' => 'entity:node/1:en:0',
              'distance' => 0.9,
            ],
            [
              'id' => 'chunk-2',
              'drupal_entity_id' => 'entity:node/1:en',
              'drupal_long_id' => 'entity:node/1:en:1',
              'distance' => 0.8,
            ],
          ];
        }
        // Second iteration returns fresh, distinct entities.
        return [
          [
            'id' => 'chunk-3',
            'drupal_entity_id' => 'entity:node/2:en',
            'drupal_long_id' => 'entity:node/2:en:0',
            'distance' => 0.7,
          ],
          [
            'id' => 'chunk-4',
            'drupal_entity_id' => 'entity:node/3:en',
            'drupal_long_id' => 'entity:node/3:en:0',
            'distance' => 0.6,
          ],
        ];
      });
    $this->setVdbClient($vdb_client);

    $query = $this->mockQueryWithoutVectorInput();
    $params = ['collection_name' => 'test', 'output_fields' => ['id']];
    $results = [];

    $meta = $this->invokeDoSearchWithIteration($query, $params, TRUE, $results, $start_limit, $start_offset);

    $this->assertSame('limit', $meta['reason']);
    $this->assertCount(2, $results);

    // Neither fetch doubles the batch size when access is bypassed.
    $this->assertSame($start_limit, $captured_calls[0]['limit']);
    $this->assertSame($start_limit, $captured_calls[1]['limit']);

    // The second fetch must skip past the window already consumed by the
    // first iteration.
    $this->assertSame(0, $captured_calls[0]['offset']);
    $this->assertSame($start_limit, $captured_calls[1]['offset']);
  }

  /**
   * Tests that access-check searches still double the batch size.
   *
   * Confirms the offset still advances by that doubled amount, preserving
   * pre-fix behavior for the !$bypass_access path.
   *
   * @covers ::doSearchWithIteration
   */
  public function testAccessCheckSearchDoublesBatchAndAdvancesOffset(): void {
    $start_limit = 2;
    $start_offset = 0;

    $captured_calls = [];
    $vdb_client = $this->createMock(AiVdbProviderInterface::class);
    $vdb_client->expects($this->exactly(2))
      ->method('querySearch')
      ->willReturnCallback(function (string $collection_name, array $output_fields, mixed $filters = '', int $limit = 10, int $offset = 0, string $database = 'default') use (&$captured_calls) {
        $captured_calls[] = ['limit' => $limit, 'offset' => $offset];
        if (count($captured_calls) === 1) {
          // Both already excluded from a previous batch: nothing new found,
          // so no access check is triggered and the search must continue.
          return [
            [
              'id' => 'chunk-1',
              'drupal_entity_id' => 'entity:node/1:en',
              'drupal_long_id' => 'entity:node/1:en:0',
              'distance' => 0.9,
            ],
            [
              'id' => 'chunk-2',
              'drupal_entity_id' => 'entity:node/2:en',
              'drupal_long_id' => 'entity:node/2:en:0',
              'distance' => 0.8,
            ],
          ];
        }
        // Reached the end of available results.
        return [];
      });
    $this->setVdbClient($vdb_client);

    $query = $this->mockQueryWithoutVectorInput();
    $params = ['collection_name' => 'test', 'output_fields' => ['id']];
    $results = [];
    $already_excluded = ['entity:node/1:en', 'entity:node/2:en'];

    $meta = $this->invokeDoSearchWithIteration($query, $params, FALSE, $results, $start_limit, $start_offset, $already_excluded);

    $this->assertSame('reached_end', $meta['reason']);
    $this->assertCount(0, $results);

    // The batch size is doubled when access checks are in play.
    $this->assertSame($start_limit * 2, $captured_calls[0]['limit']);
    $this->assertSame($start_limit * 2, $captured_calls[1]['limit']);

    // The offset advances by the doubled batch size, not by $start_limit.
    $this->assertSame(0, $captured_calls[0]['offset']);
    $this->assertSame($start_limit * 2, $captured_calls[1]['offset']);
  }

  /**
   * Tests that a max_pager_iterations of 0 stops after the first fetch.
   *
   * Companion regression test for #3584030: ::search() must treat 0 as an
   * explicit, meaningful override (fetch once, never retry) rather than
   * falling back to the backend default because 0 is falsy.
   *
   * @covers ::doSearchWithIteration
   */
  public function testZeroMaxAccessRetriesStopsAfterFirstFetch(): void {
    $start_limit = 5;

    $vdb_client = $this->createMock(AiVdbProviderInterface::class);
    $vdb_client->expects($this->once())
      ->method('querySearch')
      ->willReturn([
        [
          'id' => 'chunk-1',
          'drupal_entity_id' => 'entity:node/1:en',
          'drupal_long_id' => 'entity:node/1:en:0',
          'distance' => 0.9,
        ],
        [
          'id' => 'chunk-2',
          'drupal_entity_id' => 'entity:node/2:en',
          'drupal_long_id' => 'entity:node/2:en:0',
          'distance' => 0.8,
        ],
      ]);
    $this->setVdbClient($vdb_client);

    // Simulate what ::search() does when the query option is explicitly 0.
    $max_access_retries_property = new \ReflectionProperty(SearchApiAiSearchBackend::class, 'maxAccessRetries');
    $max_access_retries_property->setAccessible(TRUE);
    $max_access_retries_property->setValue($this->backend, 0);

    $query = $this->mockQueryWithoutVectorInput();
    $params = ['collection_name' => 'test', 'output_fields' => ['id']];
    $results = [];

    $meta = $this->invokeDoSearchWithIteration($query, $params, TRUE, $results, $start_limit, 0);

    // Only 2 distinct results were found, well short of the limit of 5, but
    // the search must not retry because max_pager_iterations is 0.
    $this->assertSame('max_retries', $meta['reason']);
    $this->assertCount(2, $results);
  }

}
