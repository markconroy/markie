<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInput;
use Drupal\ai\OperationType\Embeddings\EmbeddingsCollectionInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ai\AiVdbProviderInterface;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\ai\Enum\EmbeddingStrategyIndexingOptions;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai_search\Base\EmbeddingStrategyPluginBase;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Base class for the embedding strategies.
 */
class EmbeddingBase extends EmbeddingStrategyPluginBase implements EmbeddingStrategyInterface {

  /**
   * The maximum percentage that contextual content is allowed to take.
   *
   * The rest of the space is consumed by the main field data; however, we
   * prepend the title and basic contextual content to give context to each
   * chunk. 30 in this case is 30%, allowing 70% of the space to be taken by the
   * main field data.
   *
   * @var int
   */
  protected int $contextualContentMaxPercentage = 30;

  /**
   * {@inheritDoc}
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);
    [$title, $contextual_content, $main_content] = $this->groupFieldData($fields, $index);
    $title = $this->resolveEntityTitle($title, $fields, $search_api_item);
    $title_in_contextual = $this->isTitleInContextual($fields, $index);
    $chunks = $this->getChunks($title, $main_content, $contextual_content, $title_in_contextual, $index);
    $metadata = $this->buildBaseMetadata($fields, $index);
    $raw_embeddings = $this->getRawEmbeddings($chunks);
    $embeddings = [];
    foreach ($chunks as $key => $chunk) {
      if (!isset($raw_embeddings[$key])) {
        continue;
      }
      $metadata = $this->addContentToMetadata($metadata, $chunk, $index);
      $embedding = [
        'id' => $search_api_item->getId() . ':' . $key,
        'values' => $raw_embeddings[$key],
        'metadata' => $metadata,
      ];
      $embeddings[] = $embedding;
    }

    return $embeddings;
  }

  /**
   * Computes chunks for a full search API item, for use by the tracker.
   *
   * This method is on EmbeddingBase only — not on EmbeddingStrategyInterface —
   * to avoid a breaking interface change in 1.0.x. The tracker guards its call
   * with method_exists() so strategies that do not extend EmbeddingBase degrade
   * gracefully.
   *
   * @param string $embedding_engine
   *   The embedding engine.
   * @param array $configuration
   *   The strategy configuration (chunk_size, overlap, etc.).
   * @param array $fields
   *   The Search API fields.
   * @param \Drupal\search_api\Item\ItemInterface $search_api_item
   *   The Search API item.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return string[]
   *   The array of chunks for this item.
   */
  public function computeItemChunks(
    string $embedding_engine,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array {
    // $configuration is the strategy configuration, not the backend
    // configuration, so chat_model is never present in it. 'gpt-3.5' is used
    // as the tokenizer model — the difference is marginal for chunk counting.
    $this->init($embedding_engine, 'gpt-3.5', $configuration);
    [$title, $contextual_content, $main_content] = $this->groupFieldData($fields, $index);
    $title = $this->resolveEntityTitle($title, $fields, $search_api_item);
    $title_in_contextual = $this->isTitleInContextual($fields, $index);
    return $this->getChunks($title, $main_content, $contextual_content, $title_in_contextual, $index);
  }

  /**
   * Extracts the entity title, preferring the entity object over fields.
   *
   * Falls back to $fields_title when the entity cannot be loaded or has no
   * label key, so that indexing continues normally on any failure.
   *
   * @param string $fields_title
   *   The title already extracted from indexed fields (fallback value).
   * @param array $fields
   *   The Search API fields.
   * @param \Drupal\search_api\Item\ItemInterface $search_api_item
   *   The Search API item.
   *
   * @return string
   *   The resolved title.
   */
  protected function resolveEntityTitle(string $fields_title, array $fields, ItemInterface $search_api_item): string {
    try {
      $label_key = '';
      foreach ($fields as $field) {
        if ($field instanceof FieldInterface) {
          $datasource = $field->getDatasource();
          if ($datasource) {
            $entity_type = $this->entityTypeManager->getDefinition($datasource->getEntityTypeId());
            $label_key = $entity_type->getKey('label');
            break;
          }
        }
      }
      if (!$label_key) {
        return $fields_title;
      }
      $entity = $search_api_item->getOriginalObject()->getValue();
      if (!$entity instanceof EntityInterface) {
        return $fields_title;
      }
      $title_value = $entity->get($label_key)->value ?? $entity->label();
      if (!empty($title_value)) {
        return is_string($title_value) ? $title_value : (string) $title_value;
      }
    }
    catch (\Exception $e) {
      // Any failure falls through to the field-extracted title.
    }
    return $fields_title;
  }

  /**
   * Returns TRUE if the entity's label field is indexed as Contextual Content.
   *
   * When TRUE, the automatic title header should be suppressed to avoid
   * duplicating the title in every chunk.
   *
   * @param array $fields
   *   The Search API fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return bool
   *   TRUE if the label field is configured as Contextual Content.
   */
  protected function isTitleInContextual(array $fields, IndexInterface $index): bool {
    $label_key = '';
    foreach ($fields as $field) {
      if ($field instanceof FieldInterface) {
        $datasource = $field->getDatasource();
        if ($datasource) {
          $entity_type = $this->entityTypeManager->getDefinition($datasource->getEntityTypeId());
          $label_key = $entity_type->getKey('label');
          break;
        }
      }
    }
    if (!$label_key) {
      return FALSE;
    }
    $index_config = $this->configFactory->get('ai_search.index.' . $index->id())->getRawData();
    $indexing_options = $index_config['indexing_options'] ?? [];
    foreach ($fields as $field) {
      if (
        $field instanceof FieldInterface
        && $field->getPropertyPath() == $label_key
        && isset($indexing_options[$field->getFieldIdentifier()]['indexing_option'])
        && $indexing_options[$field->getFieldIdentifier()]['indexing_option'] === EmbeddingStrategyIndexingOptions::ContextualContent->getKey()
      ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the raw embeddings.
   *
   * Uses multi embedding when supported by the provider to reduce API calls.
   * Multiple chunks are sent in a single request when possible. Large numbers
   * of chunks are split into smaller batches to avoid API limits.
   *
   * @param array $chunks
   *   The text chunks.
   *
   * @return array
   *   The raw embeddings.
   */
  protected function getRawEmbeddings(array $chunks): array {
    $raw_embeddings = [];

    /** @var \Drupal\ai\OperationType\Embeddings\EmbeddingsInterface $embedding_llm */
    $embedding_llm = $this->embeddingLlm;

    // First pass: validate and convert chunks to UTF-8.
    $valid_chunks = [];
    $chunk_index_map = [];

    foreach ($chunks as $original_index => $chunk) {
      // If not already UTF8, attempt to convert.
      if (!Unicode::validateUtf8($chunk)) {
        if ($encoding = Unicode::encodingFromBOM($chunk)) {
          $utf8_chunk = Unicode::convertToUtf8($chunk, $encoding);
          if ($utf8_chunk === FALSE) {

            // Failed to convert, continue to next embedding but add warning
            // to the logs.
            $this->messenger->addWarning($this->t('Failed to convert chunk to UTF8: @chunk'), [
              '@chunk' => $chunk,
            ]);
            $logger = $this->loggerChannelFactory->get('ai_search');
            $logger->warning('Failed to convert chunk to UTF8: @chunk', [
              '@chunk' => $chunk,
            ]);
            continue;
          }
          else {
            $chunk = $utf8_chunk;
          }
        }
        else {

          // Failed to determine encoding to convert from.
          $this->messenger->addWarning($this->t('Failed to determine non-UTF8 encoding to attempt to auto-convert chunk: @chunk', [
            '@chunk' => $chunk,
          ]));
          $logger = $this->loggerChannelFactory->get('ai_search');
          $logger->warning('Failed to determine non-UTF8 encoding to attempt to auto-convert chunk: @chunk', [
            '@chunk' => $chunk,
          ]);
          continue;
        }
      }

      // Only proceed if we have a valid chunk.
      if ($chunk) {
        $chunk_index_map[count($valid_chunks)] = $original_index;
        $valid_chunks[] = $chunk;
      }
    }

    // If no valid chunks, return empty array.
    if (empty($valid_chunks)) {
      return [];
    }
    $tags = ['ai_search', 'indexing'];
    if ($this->skipModeration) {
      $tags[] = 'skip_moderation';
    }

    // Use multi embedding for multiple chunks when the provider opts in by
    // implementing EmbeddingsCollectionInterface. instanceof safely returns
    // FALSE on older drupal/ai releases where the interface does not exist, so
    // we transparently fall through to single-chunk processing.
    if (count($valid_chunks) > 1 && $embedding_llm->getPlugin() instanceof EmbeddingsCollectionInterface) {
      $batches = array_chunk($valid_chunks, $this->embeddingCollectionSize, TRUE);

      foreach ($batches as $batch_chunks) {
        try {
          // Re-index batch chunks to 0-based for this batch.
          $batch_texts = array_values($batch_chunks);
          $batch_keys = array_keys($batch_chunks);

          $input = new EmbeddingsCollectionInput($batch_texts);
          $result = $embedding_llm->embeddingsCollection(
            $input,
            $this->modelId,
            $tags,
          );
          $batch_embeddings = $result->getNormalized();

          // Map embeddings back to original chunk indices.
          foreach ($batch_embeddings as $batch_index => $embedding) {
            $valid_chunk_index = $batch_keys[$batch_index] ?? NULL;
            if ($valid_chunk_index !== NULL && isset($chunk_index_map[$valid_chunk_index])) {
              $raw_embeddings[$chunk_index_map[$valid_chunk_index]] = $embedding;
            }
          }
        }
        catch (\Exception $e) {
          // If batch embedding fails, fall back to single-chunk processing
          // for this batch only.
          $logger = $this->loggerChannelFactory->get('ai_search');
          $logger->warning('Batch embedding failed for @count chunks, falling back to single-chunk processing: @message', [
            '@count' => count($batch_chunks),
            '@message' => $e->getMessage(),
          ]);

          foreach ($batch_chunks as $valid_chunk_index => $chunk) {
            $input = new EmbeddingsInput($chunk);
            try {
              $raw_embeddings[$chunk_index_map[$valid_chunk_index]] = $embedding_llm->embeddings(
                $input,
                $this->modelId,
                $tags,
              )->getNormalized();
            }
            catch (\Exception $e) {
              $logger->warning('Failed to embed chunk: @message', [
                '@message' => $e->getMessage(),
              ]);
            }
          }
        }
      }
    }
    else {
      // Single chunk - embed directly without batching.
      foreach ($valid_chunks as $batch_index => $chunk) {
        // Normalize the chunk before embedding it.
        $input = new EmbeddingsInput($chunk);
        try {
          $raw_embeddings[$chunk_index_map[$batch_index]] = $embedding_llm->embeddings(
            $input,
            $this->modelId,
            $tags,
          )->getNormalized();
        }
        catch (\Exception $e) {
          $logger = $this->loggerChannelFactory->get('ai_search');
          $logger->warning('Failed to embed chunk: @message', [
            '@message' => $e->getMessage(),
          ]);
        }
      }
    }

    return array_filter($raw_embeddings);
  }

  /**
   * Group the fields into title, contextual content, and main content.
   *
   * @param array $fields
   *   The Search API fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The title, contextual content, and main content.
   */
  public function groupFieldData(array $fields, IndexInterface $index): array {
    $title = '';
    $contextual_content = '';
    $main_content = '';
    $index_config = $this->configFactory->get('ai_search.index.' . $index->id())->getRawData();
    $indexing_options = $index_config['indexing_options'] ?? [];
    $allowed_options = [
      EmbeddingStrategyIndexingOptions::MainContent->getKey(),
      EmbeddingStrategyIndexingOptions::ContextualContent->getKey(),
    ];
    foreach ($fields as $field) {

      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      // Ensure that we only operate on Main Content and Contextual Content
      // here.
      if (
        !$field instanceof FieldInterface
        || !isset($indexing_options[$field->getFieldIdentifier()]['indexing_option'])
        || !in_array($indexing_options[$field->getFieldIdentifier()]['indexing_option'], $allowed_options, TRUE)
      ) {
        continue;
      }
      $label_key = '';

      // Get the label field.
      $entity = $field->getDatasource();
      if ($entity) {
        $entity_type = $this->entityTypeManager->getDefinition($entity->getEntityTypeId());
        $label_key = $entity_type->getKey('label');
      }

      // Get and flatten the value to prepare for conversion to vector.
      $value = $this->getValue($field, TRUE);
      if (is_array($value)) {
        $value = implode(', ', $value);
      }

      // The title field.
      if ($field->getPropertyPath() == $label_key) {
        $title = $value;
      }

      // Determine whether this is the main content to be chunked or the
      // contextual content to be prepended to every chunk to provide additional
      // context.
      switch ($indexing_options[$field->getFieldIdentifier()]['indexing_option']) {
        case 'main_content':
          $main_content .= $value . "\n\n";
          break;

        case 'contextual_content':
          $contextual_content .= $field->getLabel() . ": " . $value . "\n\n";
          break;
      }
    }
    return [
      $title,
      $contextual_content,
      $main_content,
    ];
  }

  /**
   * Get the text chunks.
   *
   * @param string $title
   *   The title content.
   * @param string $main_content
   *   The main field content.
   * @param string $contextual_content
   *   The contextual content.
   * @param bool $title_in_contextual
   *   Whether title is explicitly added as contextual content.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   The Search API index.
   *
   * @return string[]
   *   The array of chunks from the text chunker.
   */
  protected function getChunks(string $title, string $main_content, string $contextual_content, bool $title_in_contextual = FALSE, ?IndexInterface $index = NULL): array {
    // This determines the available space in each chunk used by contextual
    // content vs the main fields. See the description for
    // contextual content max percentage for more details.
    $max_contextual_content = $this->contextualContentMaxPercentage / 100;
    $max_main_fields = 1 - $max_contextual_content;

    // Empty the title if it is specifically meant to be excluded OR it is
    // already in the contextual content. In these cases we do not want it
    // being part of the calculation for chunking.
    $exclude_title = FALSE;
    if ($index !== NULL) {
      $index_config = $this->configFactory->get('ai_search.index.' . $index->id())->getRawData();
      $exclude_title = $index_config['exclude_title'] ?? FALSE;
    }
    if ($title_in_contextual || $exclude_title) {
      $title = '';
    }

    $full_text = $this->prepareChunkText($title, $main_content, $contextual_content);
    $total_tokens = $this->tokenizer->countTokens($full_text);
    if ($total_tokens <= $this->chunkSize) {
      // Ideal situation, all fits in a single embedding.
      $chunks = [$full_text];
    }
    else {
      $chunks = [];
      $contextual_text = $this->prepareChunkText($title, '', $contextual_content);
      $contextual_tokens = $this->tokenizer->countTokens($contextual_text);

      if ($contextual_tokens < ($this->chunkSize * $max_contextual_content)) {
        // Contextual content is small enough. Chunk only the main content.
        $main_chunks = $this->textChunker->chunkText(
          $main_content,
          (int) ($this->chunkSize * $max_main_fields),
          $this->chunkMinOverlap
        );
        foreach ($main_chunks as $main_chunk) {
          $chunks[] = $this->prepareChunkText($title, $main_chunk, $contextual_content);
        }
      }
      else {
        // Both contextual content and main fields need chunking.
        $title_tokens = !empty($title) ? $this->tokenizer->countTokens($title) : 0;
        $available_chunk_size = $this->chunkSize - $title_tokens;
        $contextual_chunk_size = (int) ($available_chunk_size * $max_contextual_content);
        $main_chunk_size = (int) ($available_chunk_size * $max_main_fields);
        $contextual_min_overlap = max(1, intval($this->chunkMinOverlap * $max_contextual_content));

        $contextual_chunks = $this->textChunker->chunkText(
          $contextual_content,
          $contextual_chunk_size,
          $contextual_min_overlap
        );
        $main_chunks = $this->textChunker->chunkText(
          $main_content,
          $main_chunk_size,
          $this->chunkMinOverlap
        );
        foreach ($main_chunks as $main_chunk) {
          foreach ($contextual_chunks as $contextual_chunk) {
            $chunks[] = $this->prepareChunkText($title, $main_chunk, $contextual_chunk);
          }
        }
      }
    }
    return array_filter($chunks);
  }

  /**
   * Render the chunks.
   *
   * @param string $title
   *   The title content.
   * @param string $main_chunk
   *   The main field content.
   * @param string $contextual_chunk
   *   The contextual content.
   *
   * @return string
   *   The rendered chunk.
   */
  protected function prepareChunkText(string $title, string $main_chunk, string $contextual_chunk): string {
    $parts = [];
    // Only render the title if it is not empty.
    if (!empty($title)) {
      $parts[] = '# ' . strtoupper($title);
    }
    $parts[] = $main_chunk;
    if (!empty($contextual_chunk)) {
      $parts[] = $contextual_chunk;
    }
    return implode("\n\n", $parts);
  }

  /**
   * Build the base metadata from filterable attributes.
   *
   * This metadata can be used for basic filtering. More advanced filtering
   * can be done by combining traditional database or SOLR search with vector
   * database search. See the documentation pages for more details.
   *
   * @param array $fields
   *   The Search API configured fields.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The metadata to attach to the vector database record.
   */
  public function buildBaseMetadata(array $fields, IndexInterface $index): array {
    $metadata = [];
    $index_config = $this->configFactory->get('ai_search.index.' . $index->id())
      ->getRawData();
    $indexing_options = $index_config['indexing_options'];
    foreach ($fields as $field) {

      // The fields original comes from the Search API
      // ItemInterface::getFields() method. Ensure that is still the case.
      // Ensure that we only operate on Filterable Attributes here.
      if (
        !$field instanceof FieldInterface
        || !isset($indexing_options[$field->getFieldIdentifier()]['indexing_option'])
        || $indexing_options[$field->getFieldIdentifier()]['indexing_option'] !== EmbeddingStrategyIndexingOptions::Attributes->getKey()
      ) {
        continue;
      }
      $metadata[$field->getFieldIdentifier()] = $this->getValue($field, FALSE);
    }
    return $metadata;
  }

  /**
   * Maybe add the content chunk itself to the metadata.
   *
   * @param array $metadata
   *   The metadata prepared thus far.
   * @param string $content
   *   The Main Content chunk to store.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index.
   *
   * @return array
   *   The metadata to attach to the vector database record.
   */
  public function addContentToMetadata(array $metadata, string $content, IndexInterface $index): array {
    $ai_search_index_config = $this->configFactory->get('ai_search.index.' . $index->id())
      ->getRawData();
    if (
      !isset($ai_search_index_config['exclude_chunk_from_metadata'])
      || !$ai_search_index_config['exclude_chunk_from_metadata']
    ) {
      $metadata['content'] = $content;
    }
    return $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function fits(AiVdbProviderInterface $vdb_provider): bool {
    // @todo Implement fits() method.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(EmbeddingStrategyCapability $capability): bool {
    // At this time we are flagging that no strategies support the one
    // available capability.
    return FALSE;
  }

  /**
   * Concatenates multi-value fields.
   *
   * @param \Drupal\search_api\Item\FieldInterface $field
   *   The Search API field.
   * @param bool $convert_to_label
   *   Convert entity reference target IDs to labels.
   *
   * @return int|string|bool|float
   *   The field value.
   */
  protected function getValue(FieldInterface $field, bool $convert_to_label): int|array|string|bool|float {
    $values = $field->getValues();
    try {

      // If the field type is a reference field and its intended to be rendered
      // as fulltext or a string.
      $definition = $field->getDataDefinition();
      $settings = $definition->getSettings();
      if (
        $convert_to_label
        && in_array($field->getType(), ['fulltext', 'string'])
        && $definition->getDataType() === 'field_item:entity_reference'
        && !empty($settings['target_type'])
      ) {

        // If we can get the entity storage and verify the first entity is
        // an entity, clear the values and start replacing them with the labels.
        $storage = $this->entityTypeManager->getStorage($settings['target_type']);
        $entities = $storage->loadMultiple($values);
        if ($entities && reset($entities) instanceof EntityInterface) {
          $values = [];
          foreach ($entities as $entity) {
            if (!$entity instanceof EntityInterface) {
              continue;
            }
            $values[$entity->id()] = $entity->label();
          }
        }
      }
    }
    catch (\Exception $exception) {
      // Do nothing, we can just index the values for this type of field.
    }

    // Always composite if field supports multiple. Otherwise, if the field is
    // a single value, we can choose base on the field type At some point we
    // probably need to consider what field types the Vector Database supports
    // as metadata, but for now let's assume, strings, floats, integers, and
    // boolean values are fine for all.
    if (in_array($field->getType(), ['date', 'boolean', 'integer']) && count($values) === 1) {
      return (int) reset($values);
    }
    elseif (in_array($field->getType(), ['boolean']) && count($values) === 1) {
      return (bool) reset($values);
    }
    elseif (in_array($field->getType(), ['decimal']) && count($values) === 1) {
      return (float) reset($values);
    }
    elseif (count($values) == 1) {
      if ($convert_to_label) {
        return $this->converter->convert((string) reset($values));
      }
      return (string) reset($values);
    }
    elseif (count($values) > 1) {

      // Some Vector Databases support arrays, return that in the metadata
      // and leave it to the Provider to flatten if needed.
      $parts = [];
      foreach ($values as $value) {
        if (in_array($field->getType(), ['date', 'boolean', 'integer'])) {
          $parts[] = (int) $this->converter->convert((string) $value);
        }
        else {
          if ($convert_to_label) {
            $parts[] = $this->converter->convert((string) reset($values));
          }
          else {
            $parts[] = (string) $value;
          }
        }
      }
      return $parts;
    }
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function getConfigurationSubform(array $configuration): array {
    if (empty($configuration)) {
      $configuration = $this->getDefaultConfigurationValues();
    }
    $form = parent::getConfigurationSubform($configuration);
    $form['contextual_content_max_percentage'] = [
      '#title' => $this->t('Contextual content maximum percentage'),
      '#description' => $this->t('Title and other contextual content are prepended to all chunks to provide context. This setting defines the maximum space they are allowed to take up. Setting to 30 means 30% of the chunk is allowed to be Contextual Content, leaving 70% for the Main Content information. Defaults to 30% if left blank.'),
      '#required' => TRUE,
      '#type' => 'number',
      '#min' => 1,
      '#max' => 99,
      '#default_value' => $configuration['contextual_content_max_percentage'] ?? '30',
      '#field_suffix' => '%',
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function init(string $embedding_engine, string $chat_model, array $configuration): void {
    parent::init($embedding_engine, $chat_model, $configuration);
    if (!empty($configuration['contextual_content_max_percentage'])) {
      $this->contextualContentMaxPercentage = $configuration['contextual_content_max_percentage'];
    }
  }

}
