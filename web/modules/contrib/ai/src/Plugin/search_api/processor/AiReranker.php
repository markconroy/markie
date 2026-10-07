<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Rerank\ReRankInput;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Plugin\search_api\data_type\value\TextValueInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\ResultSetInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Re-orders search results using an AI reranking model.
 */
#[SearchApiProcessor(
  id: 'ai_reranker',
  label: new TranslatableMarkup('AI Reranker'),
  description: new TranslatableMarkup('Re-orders search results using an AI reranking model.'),
  stages: [
    'postprocess_query' => 0,
  ],
)]
class AiReranker extends ProcessorPluginBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  use PluginFormTrait;
  use StringTranslationTrait;

  /**
   * Extra-data key for explicitly declared document text to rerank on.
   *
   * Backends (for example ai_search in chunk mode) should set this key on a
   * result item when the configured indexed fields are not the text that
   * should be sent to the reranker. AI core never reads backend-specific
   * query options or generic keys such as "content".
   */
  public const DOCUMENT_TEXT_EXTRA_DATA_KEY = 'ai_document_text';

  /**
   * The AI provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderPluginManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->aiProviderPluginManager = $container->get('ai.provider');
    $instance->logger = $container->get('logger.factory')->get('ai');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    // Only offer the processor when at least one installed provider supports
    // the rerank operation. Without one the configuration form would be a dead
    // end (a required select with no valid option).
    $manager = \Drupal::service('ai.provider');
    $options = $manager->getSimpleProviderModelOptions('rerank', FALSE);
    return !empty($options);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'provider_id' => '',
      'model_id' => '',
      'top_n' => 0,
      'source_fields' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $provider_model_options = $this->aiProviderPluginManager->getSimpleProviderModelOptions('rerank');

    $saved_key = '';
    if (!empty($this->configuration['provider_id']) && !empty($this->configuration['model_id'])) {
      $saved_key = $this->configuration['provider_id'] . '__' . $this->configuration['model_id'];
    }

    // Search API builds this configuration form for every processor that
    // supports the index, whether enabled or not (see
    // \Drupal\search_api\Form\IndexProcessorsForm::form()), but only
    // validates it for enabled processors (see
    // IndexProcessorsForm::validateForm()). Marking these elements
    // #required would therefore block saving the Processors form for the
    // whole index whenever AI Reranker is disabled. Required-ness is
    // enforced in ::validateConfigurationForm() instead, which only runs
    // when the processor is actually enabled. The #states binding below
    // keeps the visual "required" asterisk tied to that same enabled state.
    $status_selector = ':input[name="status[' . $this->getPluginId() . ']"]';

    $form['provider_model'] = [
      '#type' => 'select',
      '#title' => $this->t('AI provider and model'),
      '#description' => $this->t('Select the reranking provider and model. Only providers that support the rerank operation are listed.'),
      '#options' => $provider_model_options,
      '#default_value' => $saved_key,
      '#states' => [
        'required' => [
          $status_selector => ['checked' => TRUE],
        ],
      ],
    ];

    $form['top_n'] = [
      '#type' => 'number',
      '#title' => $this->t('Top N results'),
      '#description' => $this->t('How many results to ask the reranker to prioritize. This is the number of top results the model ranks; it does not truncate the result set and does not change the total result count shown by the pager. Set to 0 to rank all results on the page.'),
      '#default_value' => $this->configuration['top_n'] ?? 0,
      '#min' => 0,
    ];

    $field_options = [];
    foreach ($this->index->getFields() as $field_id => $field) {
      $field_options[$field_id] = $field->getLabel();
    }

    $form['source_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Source fields'),
      '#description' => $this->t('Select which fields to concatenate as the document text sent to the reranker. At least one field is required. Choose the fields that best represent the content (for example Title and Body).'),
      '#options' => $field_options,
      '#default_value' => $this->configuration['source_fields'] ?? [],
      '#states' => [
        'required' => [
          $status_selector => ['checked' => TRUE],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $provider_model = (string) ($form_state->getValue('provider_model') ?? '');
    $parts = explode('__', $provider_model, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
      $form_state->setError($form['provider_model'], $this->t('Select a valid AI provider and model that supports the rerank operation.'));
    }

    $source_fields = array_filter((array) ($form_state->getValue('source_fields') ?? []));
    if (empty($source_fields)) {
      $form_state->setError($form['source_fields'], $this->t('Select at least one source field to send to the reranker.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $provider_model = $form_state->getValue('provider_model') ?? '';
    $parts = explode('__', $provider_model, 2);
    $this->configuration['provider_id'] = $parts[0] ?? '';
    $this->configuration['model_id'] = $parts[1] ?? '';
    $this->configuration['top_n'] = (int) ($form_state->getValue('top_n') ?? 0);
    $source_fields = $form_state->getValue('source_fields') ?? [];
    $this->configuration['source_fields'] = array_keys(array_filter($source_fields));
  }

  /**
   * {@inheritdoc}
   */
  public function postprocessSearchResults(ResultSetInterface $results): void {
    $query = $results->getQuery();
    $keys = $query->getKeys();

    // No query string — nothing to rerank.
    if (empty($keys)) {
      return;
    }

    $query_string = $this->flattenKeys($keys);
    if ($query_string === '') {
      return;
    }

    $result_items = $results->getResultItems();
    if (empty($result_items)) {
      return;
    }

    $provider_id = $this->configuration['provider_id'] ?? '';
    $model_id = $this->configuration['model_id'] ?? '';

    if (empty($provider_id) || empty($model_id)) {
      return;
    }

    try {
      $provider = $this->aiProviderPluginManager->createInstance($provider_id);

      if (!in_array('rerank', $provider->getSupportedOperationTypes())) {
        $this->logger->warning('AI Reranker: provider @id does not support the rerank operation.', ['@id' => $provider_id]);
        return;
      }

      // Build document strings from the configured source fields.
      $documents = [];
      $item_keys = [];
      $missing_text = 0;
      foreach ($result_items as $item_id => $item) {
        $document = $this->extractItemText($item);
        if ($document === '') {
          // Do not fall back to the internal item ID: sending it to an external
          // API leaks internal identifiers and produces meaningless scores. Use
          // an empty document instead so index alignment is preserved.
          $missing_text++;
        }
        $documents[] = $document;
        $item_keys[] = $item_id;
      }

      // Warn once per run, not per item, and never with item IDs.
      if ($missing_text > 0) {
        $this->logger->warning('AI Reranker: @count of @total result items had no text in the configured source fields. Check the AI Reranker source field configuration to improve reranking quality.', [
          '@count' => $missing_text,
          '@total' => count($result_items),
        ]);
      }

      $top_n = (int) ($this->configuration['top_n'] ?? 0);
      $input = new ReRankInput($query_string, $documents, $top_n);
      $output = $provider->rerank($input, $model_id, ['ai']);
      $reranked = $output->getNormalized();

      // Build the new ordered result set from the rerank response. The
      // normalized response is expected to be a list of items, each exposing an
      // integer "index" pointing back into the documents array (and optionally
      // a relevance score). See the ReRankInterface docblock.
      $reordered = [];
      foreach ($reranked as $ranked_item) {
        $index = $this->readRankedValue($ranked_item, 'index');
        if ($index === NULL || !isset($item_keys[$index])) {
          continue;
        }
        $key = $item_keys[$index];
        if (!isset($result_items[$key])) {
          continue;
        }
        $item = $result_items[$key];

        // Keep the item's score consistent with its new rank when the provider
        // returns one.
        $score = $this->readRankedValue($ranked_item, 'relevance_score')
          ?? $this->readRankedValue($ranked_item, 'score');
        if ($score !== NULL && is_numeric($score)) {
          $item->setScore((float) $score);
        }

        $reordered[$key] = $item;
      }

      // If the reranker returned no usable index mappings, warn and preserve
      // original order.
      if (empty($reordered)) {
        $this->logger->warning('AI Reranker: the provider returned no usable index values in its response. Results are returned in their original order. Check that the configured provider formats its rerank output correctly.');
      }

      // Note: reranking operates on the current result page only, not across
      // all pages. This is a constraint of the postprocess_query stage in
      // Search API.
      // Preserve original results for any items not returned by the reranker.
      foreach ($result_items as $item_id => $item) {
        if (!isset($reordered[$item_id])) {
          $reordered[$item_id] = $item;
        }
      }

      // Only the order (and scores) of the current page are changed. The total
      // result count is intentionally left untouched so pagination keeps
      // working; see the "Top N results" setting help text.
      $results->setResultItems($reordered);
    }
    catch (\Throwable $e) {
      // Degrade gracefully: never break the search request for the end user.
      // But an exception here (auth failure, unreachable provider, bad model)
      // is a real misconfiguration, not the benign "no usable data" no-op that
      // is logged as a warning above. Log it at error level, with exception
      // context, so it is a distinct, admin-visible signal in the logs /
      // monitoring rather than being lost among the warnings.
      $this->logger->error('AI Reranker failed and returned results in their original order: @message Check the AI Reranker provider and model configuration on this index.', [
        '@message' => $e->getMessage(),
        'exception' => $e,
      ]);
    }
  }

  /**
   * Extracts the document text for a single result item.
   *
   * Prefers text declared via ::DOCUMENT_TEXT_EXTRA_DATA_KEY when a backend has
   * set it (for example per-chunk text). Otherwise concatenates the configured
   * source fields, unwrapping fulltext value objects.
   *
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The result item.
   *
   * @return string
   *   The extracted text, or an empty string if there was none.
   */
  protected function extractItemText($item): string {
    $declared_text = $this->fieldValueToString($item->getExtraData(self::DOCUMENT_TEXT_EXTRA_DATA_KEY));
    if ($declared_text !== '') {
      return $declared_text;
    }

    $text_parts = [];
    foreach ($this->configuration['source_fields'] as $field_id) {
      $field = $item->getField($field_id);
      if (!$field) {
        continue;
      }
      foreach ($field->getValues() as $value) {
        $text = $this->fieldValueToString($value);
        if ($text !== '') {
          $text_parts[] = $text;
        }
      }
    }

    return implode(' ', $text_parts);
  }

  /**
   * Converts a single Search API field value to a plain string.
   *
   * @param mixed $value
   *   The field value. May be a plain string, a fulltext TextValue object, or
   *   any other Stringable value.
   *
   * @return string
   *   The string representation, trimmed. Empty string if it cannot be cast.
   */
  protected function fieldValueToString(mixed $value): string {
    if (is_string($value)) {
      return trim($value);
    }
    if ($value instanceof TextValueInterface) {
      return trim($value->getText());
    }
    if (is_scalar($value)) {
      return trim((string) $value);
    }
    if ($value instanceof \Stringable) {
      return trim((string) $value);
    }
    return '';
  }

  /**
   * Reads a value from a rerank result item, which may be an array or object.
   *
   * @param mixed $ranked_item
   *   A single normalized rerank result item.
   * @param string $key
   *   The key/property to read.
   *
   * @return mixed
   *   The value, or NULL if it is not present.
   */
  protected function readRankedValue(mixed $ranked_item, string $key): mixed {
    if (is_array($ranked_item)) {
      return $ranked_item[$key] ?? NULL;
    }
    if (is_object($ranked_item)) {
      return $ranked_item->{$key} ?? NULL;
    }
    return NULL;
  }

  /**
   * Flattens a Search API keys array or string into a plain query string.
   *
   * Negated subtrees (marked with '#negation') and meta keys (those prefixed
   * with '#', such as '#conjunction') are skipped so that excluded terms are
   * not sent to the reranker as positive query text.
   *
   * @param string|array $keys
   *   The search keys from QueryInterface::getKeys().
   *
   * @return string
   *   A single query string.
   */
  protected function flattenKeys(string|array $keys): string {
    if (is_string($keys)) {
      return trim($keys);
    }

    // A negated subtree contributes no positive query text.
    if (!empty($keys['#negation'])) {
      return '';
    }

    $parts = [];
    foreach ($keys as $key => $value) {
      // Skip meta keys such as '#conjunction' and '#negation'.
      if (!Element::child($key)) {
        continue;
      }
      if (is_string($value)) {
        $parts[] = $value;
      }
      elseif (is_array($value)) {
        $nested = $this->flattenKeys($value);
        if ($nested !== '') {
          $parts[] = $nested;
        }
      }
    }

    return implode(' ', $parts);
  }

}
