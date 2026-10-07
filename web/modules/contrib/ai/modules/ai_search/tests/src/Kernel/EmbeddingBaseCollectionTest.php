<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase;
use Drupal\KernelTests\KernelTestBase;
use Drupal\test_ai_provider_mysql\Plugin\AiProvider\TestEmbeddingCollectionProvider;

/**
 * Tests multi embedding behavior of the EmbeddingBase strategy plugin.
 *
 * Uses a deterministic synthetic provider (no heavyweight model) that marks
 * vectors by code path, so the tests can assert that: the batch path is taken
 * when the provider supports it, each chunk maps to its own vector across batch
 * boundaries, and skipped (empty) chunks keep the remaining vectors aligned to
 * their original index.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
 * @group ai_search
 */
class EmbeddingBaseCollectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_search',
    'ai',
    'search_api',
    'key',
    'test_ai_provider_mysql',
  ];

  /**
   * The embedding strategy plugin instance under test.
   *
   * @var \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
   */
  protected $embeddingStrategy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_search']);

    // Reset the provider's static test state for isolation between methods.
    TestEmbeddingCollectionProvider::$failBatch = FALSE;
    TestEmbeddingCollectionProvider::$lastSingleTags = [];

    $manager = $this->container->get('ai_search.embedding_strategy');
    $this->embeddingStrategy = $manager->createInstance('contextual_chunks');
    // Small collection size so multiple batches are exercised for 5 chunks.
    $this->embeddingStrategy->init('test_collection_provider__model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
      'embedding_collection_size' => 2,
    ]);
  }

  /**
   * Invoke the protected getRawEmbeddings() with the given chunks.
   */
  protected function getRawEmbeddings(array $chunks): array {
    $class = new \ReflectionClass(EmbeddingBase::class);
    $method = $class->getMethod('getRawEmbeddings');
    $method->setAccessible(TRUE);
    return $method->invoke($this->embeddingStrategy, $chunks);
  }

  /**
   * The expected synthetic vector for a chunk, with the given path marker.
   */
  protected function expectedVector(string $text, float $marker): array {
    return [
      $marker,
      (float) strlen($text),
      (float) array_sum(array_map('ord', str_split($text))),
    ];
  }

  /**
   * The expected synthetic vector for a chunk produced by the batch path.
   */
  protected function expectedBatchVector(string $text): array {
    return $this->expectedVector($text, TestEmbeddingCollectionProvider::BATCH_MARKER);
  }

  /**
   * The batch path is used and each chunk maps to its own vector.
   *
   * @covers ::getRawEmbeddings
   */
  public function testBatchMapsEachChunkToItsVector(): void {
    $chunks = ['apple', 'banana', 'cherry', 'date', 'elderberry'];
    $result = $this->getRawEmbeddings($chunks);

    // One vector per chunk, keyed by the original index, in order.
    $this->assertSame([0, 1, 2, 3, 4], array_keys($result));
    foreach ($chunks as $i => $chunk) {
      // The batch marker proves the batch path produced this vector...
      $this->assertSame(TestEmbeddingCollectionProvider::BATCH_MARKER, $result[$i][0]);
      // ...and the full vector proves the chunk maps to its own embedding,
      // correctly across the three batches (sizes 2, 2, 1).
      $this->assertSame($this->expectedBatchVector($chunk), $result[$i], "Chunk $i ('$chunk') maps to its own vector.");
    }
  }

  /**
   * Skipped (empty) chunks keep the remaining vectors aligned to their index.
   *
   * @covers ::getRawEmbeddings
   */
  public function testSkippedChunksKeepAlignment(): void {
    // The empty middle chunk is dropped during validation.
    $chunks = ['apple', '', 'cherry'];
    $result = $this->getRawEmbeddings($chunks);

    // Keys reflect the original indices, with index 1 absent and the rest
    // still aligned to their own vectors.
    $this->assertSame([0, 2], array_keys($result));
    $this->assertSame($this->expectedBatchVector('apple'), $result[0]);
    $this->assertSame($this->expectedBatchVector('cherry'), $result[2]);
  }

  /**
   * When the batch call fails, it falls back to per-chunk embedding.
   *
   * @covers ::getRawEmbeddings
   */
  public function testFallbackToSinglePathOnBatchFailure(): void {
    TestEmbeddingCollectionProvider::$failBatch = TRUE;

    $chunks = ['apple', 'banana', 'cherry'];
    $result = $this->getRawEmbeddings($chunks);

    // All chunks still embedded and correctly keyed...
    $this->assertSame([0, 1, 2], array_keys($result));
    foreach ($chunks as $i => $chunk) {
      // ...but via the single path (SINGLE marker), proving the fallback ran.
      $this->assertSame(TestEmbeddingCollectionProvider::SINGLE_MARKER, $result[$i][0]);
      $this->assertSame($this->expectedVector($chunk, TestEmbeddingCollectionProvider::SINGLE_MARKER), $result[$i]);
    }
  }

  /**
   * The fallback path forwards the configured tags (e.g. skip_moderation).
   *
   * @covers ::getRawEmbeddings
   */
  public function testFallbackForwardsSkipModerationTag(): void {
    // Re-init with moderation skipping enabled and force the batch to fail.
    $this->embeddingStrategy->init('test_collection_provider__model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
      'embedding_collection_size' => 2,
      'skip_moderation' => TRUE,
    ]);
    TestEmbeddingCollectionProvider::$failBatch = TRUE;

    $this->getRawEmbeddings(['apple', 'banana']);

    // The single (fallback) path must have received the skip_moderation tag.
    $tags = TestEmbeddingCollectionProvider::$lastSingleTags;
    $this->assertContains('skip_moderation', $tags);
    $this->assertContains('ai_search', $tags);
  }

}
