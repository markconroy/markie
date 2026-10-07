<?php

namespace Drupal\test_embedding_strategy\Plugin\EmbeddingStrategy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * A custom embedding strategy extending EmbeddingBase, used in BC tests.
 *
 * Simulates what a third-party module does when it extends EmbeddingBase and
 * overrides getEmbedding() with the 1.0.x six-parameter signature. This must
 * continue to load and satisfy EmbeddingStrategyInterface after any backport.
 */
#[EmbeddingStrategy(
  id: 'test_custom',
  label: new TranslatableMarkup('Test Custom Embedding Strategy'),
  description: new TranslatableMarkup('Used to verify extending EmbeddingBase does not break after backport.'),
)]
class TestCustomEmbeddingStrategy extends EmbeddingBase {

  /**
   * {@inheritDoc}
   *
   * Overrides with the 1.0.x six-parameter signature to simulate a site that
   * has a custom embedding strategy. This signature must not cause a PHP error.
   *
   * The override delegates entirely to the parent. This is intentional: the
   * point is not to change behavior but to prove the signature is compatible.
   */
  // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array {
    return parent::getEmbedding(
      $embedding_engine,
      $chat_model,
      $configuration,
      $fields,
      $search_api_item,
      $index,
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getDefaultConfigurationValues(): array {
    return array_merge(parent::getDefaultConfigurationValues(), [
      'custom_option' => TRUE,
    ]);
  }

}
