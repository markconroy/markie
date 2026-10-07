<?php

namespace Drupal\test_ai_provider_mysql\Plugin\AiProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionOutput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInterface;
use Drupal\ai\OperationType\Embeddings\EmbeddingsOutput;

/**
 * A lightweight test provider with deterministic, synthetic embeddings.
 *
 * Unlike the MySQL provider, this does not load a heavyweight model, so it runs
 * fast and on any CPU. It implements BatchEmbeddingsInterface so the batch path
 * of
 * the embedding strategy can be exercised. The first vector element is a
 * marker (1.0 for single, 2.0 for batch) so tests can assert which code path
 * produced the vector; the remaining elements make the vector unique per text.
 */
#[AiProvider(
  id: 'test_collection_provider',
  label: new TranslatableMarkup('Test Embedding Collection Provider'),
)]
class TestEmbeddingCollectionProvider extends AiProviderClientBase implements EmbeddingsInterface, EmbeddingsCollectionInterface {

  /**
   * Marker value for vectors produced by the single embeddings() path.
   */
  const SINGLE_MARKER = 1.0;

  /**
   * Marker value for vectors produced by the batchEmbeddings() path.
   */
  const BATCH_MARKER = 2.0;

  /**
   * When TRUE, batchEmbeddings() throws so the per-chunk fallback is exercised.
   *
   * @var bool
   */
  public static bool $failBatch = FALSE;

  /**
   * The tags passed to the most recent single embeddings() call.
   *
   * @var array
   */
  public static array $lastSingleTags = [];

  /**
   * {@inheritdoc}
   */
  public function getApiDefinition(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return [
      'embeddings',
      'batch_embeddings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    return in_array($operation_type, ['embeddings', 'batch_embeddings'], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('test_batch_provider.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Do nothing.
  }

  /**
   * Build a deterministic vector for a string with the given marker.
   */
  protected function vector(string $text, float $marker): array {
    return [
      $marker,
      (float) strlen($text),
      (float) array_sum(array_map('ord', str_split($text !== '' ? $text : ' '))),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput {
    if ($input instanceof EmbeddingsInput) {
      $input = $input->getPrompt();
    }
    // Record the tags so tests can assert which tags reached the single path
    // (e.g. that the fallback still passes 'skip_moderation').
    self::$lastSingleTags = $tags;
    $vector = $this->vector($input, self::SINGLE_MARKER);
    return new EmbeddingsOutput($vector, $vector, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsCollection(EmbeddingsCollectionInput $input, string $model_id, array $tags = []): EmbeddingsCollectionOutput {
    // Let tests simulate a provider-side batch failure to exercise the
    // strategy's per-chunk fallback.
    if (self::$failBatch) {
      throw new \RuntimeException('Simulated batch embedding failure.');
    }
    $vectors = [];
    foreach ($input->getPrompts() as $prompt) {
      $vectors[] = $this->vector($prompt, self::BATCH_MARKER);
    }
    return new EmbeddingsCollectionOutput($vectors, $vectors, []);
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    return 3;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return ['model' => 'Test Batch Model'];
  }

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput($model_id = ''): int {
    return 1000;
  }

}
