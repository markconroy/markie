<?php

namespace Drupal\ai_search\Plugin\search_api\backend;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\AiVdbProviderPluginManager;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\ai_search\Backend\AiSearchBackendPluginBase;
use Drupal\ai_search\EmbeddingStrategyPluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * AI Search backend for search api.
 *
 * @SearchApiBackend(
 *   id = "search_api_ai_search",
 *   label = @Translation("AI Search"),
 *   description = @Translation("Index items on Vector DB.")
 * )
 */
class SearchApiAiSearchBackend extends AiSearchBackendPluginBase implements PluginFormInterface {

  /**
   * The AI VDB Provider.
   *
   * @var \Drupal\ai\AiVdbProviderPluginManager
   */
  protected AiVdbProviderPluginManager $vdbProviderManager;

  /**
   * The AI LLM Provider.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderManager;

  /**
   * The Embedding Strategy manager.
   *
   * @var \Drupal\ai_search\EmbeddingStrategyPluginManager
   */
  protected EmbeddingStrategyPluginManager $embeddingStrategyProviderManager;

  /**
   * The tokenizer interface to get the supported token count models.
   *
   * @var \Drupal\ai\Utility\TokenizerInterface
   */
  protected TokenizerInterface $tokenizer;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current account, proxy interface.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Vector DB client.
   *
   * @var object
   */
  protected object $vdbClient;

  /**
   * Max retries for iterating for access.
   *
   * @var int
   */
  protected int $maxAccessRetries = 10;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->vdbProviderManager = $container->get('ai.vdb_provider');
    $instance->aiProviderManager = $container->get('ai.provider');
    $instance->embeddingStrategyProviderManager = $container->get('ai_search.embedding_strategy');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->messenger = $container->get('messenger');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->currentUser = $container->get('current_user');
    $instance->tokenizer = $container->get('ai.tokenizer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDiscouragedProcessors(): array {
    return [
      // We convert to markdown which LLMs understand.
      'html_filter',
      // Boosting does not apply here.
      'number_field_boost',
      // There is no point, vectors inherently do not need this.
      'stemmer',
      // We use our own more advanced embedding strategies.
      'tokenizer',
      // Boosting does not apply here.
      'type_boost',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    $config = parent::defaultConfiguration();
    if (!isset($config['database'])) {
      $config['database'] = NULL;
    }
    if (!isset($config['database_settings'])) {
      $config['database_settings'] = [];
    }
    if (!isset($config['embedding_strategy'])) {
      $config['embedding_strategy'] = NULL;
    }
    return $config;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // If a subform is received, we want the full form state.
    if ($form_state instanceof SubformStateInterface) {
      $form_state = $form_state->getCompleteFormState();
    }

    // If no provider is installed we can't do anything.
    $errors = [];
    if (!$this->aiProviderManager->hasProvidersForOperationType('embeddings')) {
      $errors[] = '<div class="ai-error">' . $this->t('No AI providers are installed for Embeddings calls, please %install and %configure one first.', [
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_providers')->toString(),
      ]) . '</div>';
    }

    $search_api_vdb_providers = $this->vdbProviderManager->getSearchApiProviders(TRUE);
    if (empty($search_api_vdb_providers)) {
      $errors[] = '<div class="ai-error">' . $this->t('No Vector DB providers are installed or setup for search in vectors, please %install and %configure one first.', [
        '%install' => Link::createFromRoute($this->t('install'), 'system.modules_list')->toString(),
        '%configure' => Link::createFromRoute($this->t('configure'), 'ai.admin_vdb_providers')->toString(),
      ]) . '</div>';
    }

    if (count($errors)) {
      $form['markup'] = [
        '#markup' => implode('', $errors),
      ];
      return $form;
    }

    // Get all supported models, default to gpt-3.5 model.
    $supported_models = $this->tokenizer->getSupportedModels();
    $default_model_possibilities = array_keys(array_filter($supported_models, function ($model) {
      return str_contains($model, 'gpt-3.5');
    }, ARRAY_FILTER_USE_KEY));
    $default_model = reset($default_model_possibilities);
    $form['chat_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Tokenizer chat counting model'),
      '#description' => $this->t('This is recommended to ensure the right number of tokens is calculated for the embeddings. Depending on the vector database and dimensions, the number of Tokens allowed per chunk of content differs. This service is used to count the number of tokens in your content as accurately as possible to better make use of the available space.'),
      '#default_value' => $this->configuration['chat_model'] ?? $default_model,
      '#options' => $this->tokenizer->getSupportedModels(),
      '#required' => TRUE,
      '#weight' => 2,
    ];

    $chosen_database = $this->configuration['database'] ?? NULL;
    if (!$chosen_database) {
      // Try to get from form state.
      $chosen_database = $form_state->get('database') ?? NULL;
    }

    $form['database'] = [
      '#type' => 'select',
      '#title' => $this->t('Vector Database'),
      '#options' => $search_api_vdb_providers,
      '#required' => TRUE,
      '#default_value' => $chosen_database,
      '#description' => $this->t("The Vector Database to use. This is where the generated Embeddings (vectorized representations of your content) are stored. The user's queries are then vectorized in the same manner and the mathematical distance between the query and the vectors stored in the database are compared to find the nearest results."),
      '#ajax' => [
        'callback' => [$this, 'updateVectorDatabaseSettingsForm'],
        'event' => 'change',
        'method' => 'replaceWith',
        'wrapper' => 'database-settings-wrapper',
      ],
      '#weight' => 3,
    ];

    // Container for database-specific settings.
    $form['database_settings'] = [
      '#type' => 'details',
      '#open' => FALSE,
      '#attributes' => ['id' => 'database-settings-wrapper'],
      '#title' => $this->t('Vector Database Configuration'),
      '#weight' => 4,
    ];

    // If a Vector Database has been chosen, build the custom fields.
    if ($chosen_database) {

      // Only open the settings once there is a chosen database.
      $form['database_settings']['#open'] = TRUE;

      $vdb_client = $this->vdbProviderManager->createInstance($chosen_database);
      $form['database_settings'] = $vdb_client->buildSettingsForm(
        $form['database_settings'],
        $form_state,
        $this->configuration
      );
    }

    // Add Embeddings Engine or Embeddings Strategy subform.
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * AJAX callback to update the database-specific fields.
   */
  public function updateVectorDatabaseSettingsForm(array &$form, FormStateInterface $form_state): array {
    return $form['backend_config']['database_settings'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (
      !empty($values['embeddings_engine'])
      && isset($values['embeddings_engine_configuration']['dimensions'])
      && $values['embeddings_engine_configuration']['dimensions'] <= 0
    ) {
      $form_state->setErrorByName('embeddings_engine_configuration][dimensions', $this->t('Embeddings engine configuration "dimensions" must be provided and must be greater than 0'));
    }

    if (!empty($form_state->getValue('database'))) {
      try {
        $vdb_client = $this->vdbProviderManager->createInstance($form_state->getValue('database'));
        $vdb_client->validateSettingsForm($form, $form_state);
      }
      catch (\Exception $exception) {
        $form_state->setErrorByName('database', $this->t('An error occurred: "@error"', [
          '@error' => $exception->getMessage(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
    if ($this->configuration['embedding_strategy_container']) {
      $this->configuration = array_merge($this->configuration, $this->configuration['embedding_strategy_container']);
      unset($this->configuration['embedding_strategy_container']);
    }
    parent::setConfiguration($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    if ($type === 'embeddings') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the chat model options that the tokenizer supports.
   *
   * @return array
   *   The chat model options that Tokenizer supports.
   */
  protected function getModelTokenizerOptions(): array {
    $model_options = $this->aiProviderManager->getSimpleProviderModelOptions('chat');
    $model_options = array_filter($model_options, function ($option) {
      return str_contains($option, '__');
    }, ARRAY_FILTER_USE_KEY);
    return $model_options;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
    $vdb_client = $this->vdbProviderManager->createInstance($this->configuration['database']);
    $vdb_client->submitSettingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function indexItems(IndexInterface $index, array $items): array {
    $embedding_strategy = $this->embeddingStrategyProviderManager->createInstance($this->configuration['embedding_strategy']);
    return $this->getClient()->indexItems($this->configuration, $index, $items, $embedding_strategy);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteItems(IndexInterface $index, array $item_ids): void {
    $vdb_client = $this->vdbProviderManager->createInstance($this->configuration['database']);
    $vdb_client->deleteIndexItems($this->configuration, $index, $item_ids);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function deleteAllIndexItems(IndexInterface $index, $datasource_id = NULL): void {
    $vdb_client = $this->vdbProviderManager->createInstance($this->configuration['database']);
    $vdb_client->deleteAllIndexItems($this->configuration, $index, $datasource_id);
  }

  /**
   * Set query results.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   *
   * @return void|null
   *   The results.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function search(QueryInterface $query) {
    // Check if we need to do entity access checks.
    $bypass_access = $query->getOption('search_api_bypass_access', FALSE);
    // Check if we have a custom value for the iterator.
    if ($query->getOption('search_api_ai_max_pager_iterations', 0)) {
      $this->maxAccessRetries = $query->getOption('search_api_ai_max_pager_iterations');
    }
    // Check if we should aggregate results.
    $get_chunked = $query->getOption('search_api_ai_get_chunks_result', FALSE);

    // Get index and ensure it is ready.
    if ($query->hasTag('server_index_status')) {
      return NULL;
    }
    $index = $query->getIndex();

    // Get DB Client.
    if (empty($this->configuration['database'])) {
      return NULL;
    }

    // Get query.
    $results = $query->getResults();

    // Prepare params.
    $params = [
      'collection_name' => $this->configuration['database_settings']['collection'],
      'output_fields' => ['id', 'drupal_entity_id', 'drupal_long_id', 'content'],
      // If an access check is in place, multiple iterations of the query are
      // run to attempt to reach this limit.
      'limit' => (int) $query->getOption('limit', 10),
      'offset' => (int) $query->getOption('offset', 0),
    ];

    if ($filters = $this->getClient()->prepareFilters($query)) {
      $params['filters'] = $filters;
    }

    // Conduct the search.
    $real_results = [];
    $meta_data = $this->doSearch($query, $params, $bypass_access, $real_results, $params['limit'], $params['offset']);
    // Keep track of items already added so existing result items do not get
    // overwritten by later records containing the same item.
    $stored_items = [];

    // Obtain results.
    foreach ($real_results as $match) {
      $id = $get_chunked ? $match['drupal_entity_id'] . ':' . $match['id'] : $match['drupal_entity_id'];
      $item = $this->getFieldsHelper()->createItem($index, $id);
      $item->setScore($match['distance'] ?? 1);
      $this->extractMetadata($match, $item);
      if (!$get_chunked && !in_array($item->getId(), $stored_items)) {
        $stored_items[] = $item->getId();
        $results->addResultItem($item);
      }
      else {
        $results->addResultItem($item);
      }
    }
    $results->setExtraData('real_offset', $meta_data['real_offset']);
    $results->setExtraData('reason_for_finish', $meta_data['reason']);
    // Get the last vector score.
    $results->setExtraData('current_vector_score', $meta_data['vector_score'] ?? 0);

    // Sort results.
    $sorts = $query->getSorts();
    if (!empty($sorts["search_api_relevance"])) {
      $result_items = $results->getResultItems();
      usort($result_items, function ($a, $b) use ($sorts) {
        $distance_a = $a->getScore();
        $distance_b = $b->getScore();
        return $sorts["search_api_relevance"] === 'DESC' ? $distance_b <=> $distance_a : $distance_a <=> $distance_b;
      });
      $results->setResultItems($result_items);
    }

    // Set results count.
    $results->setResultCount(count($results->getResultItems()));
  }

  /**
   * Run the search until enough items are found.
   */
  protected function doSearch(QueryInterface $query, $params, $bypass_access, &$results, $start_limit, $start_offset, $iteration = 0) {
    $params['database'] = $this->configuration['database_settings']['database_name'];
    $params['collection_name'] = $this->configuration['database_settings']['collection'];

    // Conduct the search.
    if (!$bypass_access) {
      // Double the results, if we need to run over access checks.
      $params['limit'] = $start_limit * 2;
      $params['offset'] = $start_offset + ($iteration * $start_limit * 2);
    }
    $search_words = $query->getKeys();
    if (!empty($search_words)) {
      [$provider_id, $model_id] = explode('__', $this->configuration['embeddings_engine']);
      $embedding_llm = $this->aiProviderManager->createInstance($provider_id);
      // We don't have to redo this.
      if (!isset($params['vector_input'])) {
        // Handle complex search queries, but we just normalize to string.
        // It makes no sense to do Boolean or other complex searches on vectors.
        if (is_array($search_words)) {
          if (isset($search_words['#conjunction'])) {
            unset($search_words['#conjunction']);
          }
          $search_words = implode(' ', $search_words);
        }
        $input = new EmbeddingsInput($search_words);
        $params['vector_input'] = $embedding_llm->embeddings($input, $model_id)->getNormalized();
      }
      $response = $this->getClient()->vectorSearch(...$params);
    }
    else {
      $response = $this->getClient()->querySearch(...$params);
    }

    // Obtain results.
    $i = 0;
    foreach ($response as $match) {
      $i++;
      // Do access checks.
      if (!$bypass_access && !$this->checkEntityAccess($match['drupal_entity_id'])) {
        // If we are not allowed to view this entity, we can skip it.
        continue;
      }
      // Passed.
      $results[] = $match;
      // If we found enough items, we can stop.
      if (count($results) == $start_limit) {
        return [
          'real_offset' => $start_offset + ($iteration * $start_limit * 2) + $i,
          'reason' => 'limit',
          'vector_score' => $match->distance ?? 0,
        ];
      }
    }

    // If we reach max retries, we can stop.
    if ($iteration == $this->maxAccessRetries) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'max_retries',
        'vector_score' => $match->distance ?? 0,
      ];
    }
    // If we got less then limit back, it reached the end.
    if (count($response) < $start_limit) {
      return [
        'real_offset' => $iteration * $start_limit * 2 + $i,
        'reason' => 'reached_end',
        'vector_score' => $match->distance ?? 0,
      ];
    }
    // Else we need to continue.
    return $this->doSearch($query, $params, $bypass_access, $results, $start_limit, $start_offset, $iteration + 1);
  }

  /**
   * Extract query metadata values to a result item.
   *
   * @param array $result_row
   *   The result row.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item.
   */
  public function extractMetadata(array $result_row, ItemInterface $item): void {
    foreach ($result_row as $key => $value) {
      if ($key === 'vector' || $key === 'id' || $key === 'distance') {
        continue;
      }
      $item->setExtraData($key, $value);
    }
  }

  /**
   * Get the Vector DB client instance.
   *
   * @return \Drupal\ai\AiVdbProviderInterface
   *   The Vector DB object.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  private function getClient(): object {
    if (empty($this->vdbClient)) {
      $this->vdbClient = $this->vdbProviderManager->createInstance($this->configuration['database']);
    }
    return $this->vdbClient;
  }

  /**
   * Check entity access.
   *
   * @param string $drupal_id
   *   The Drupal entity ID.
   *
   * @return bool
   *   If the entity is accessible.
   */
  private function checkEntityAccess(string $drupal_id): bool {
    [$entity_type, $id_lang] = explode('/', str_replace('entity:', '', $drupal_id));
    [$id, $lang] = explode(':', $id_lang);
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->entityTypeManager->getStorage($entity_type)->load($id);

    // If the entity fails to load, assume false.
    if (!$entity instanceof EntityInterface) {
      return FALSE;
    }

    // Get the entity translation if a specific language is requested so long
    // as the entity is translatable in the first place.
    if (
      $entity instanceof TranslatableInterface
      && $entity->hasTranslation($lang)
    ) {
      $entity = $entity->getTranslation($lang);
    }
    return $entity->access('view', $this->currentUser);
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings(): array {
    return $this->getClient()->viewIndexSettings($this->configuration['database_settings']);
  }

}
