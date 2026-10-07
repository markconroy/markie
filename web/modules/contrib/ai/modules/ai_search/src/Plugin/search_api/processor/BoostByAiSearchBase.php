<?php

namespace Drupal\ai_search\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\ServerInterface;

/**
 * Base class to combine a traditional search database with an AI one.
 *
 * The idea here is that we prepend a set of results from the AI Search to
 * re-order the Search API traditional search.
 */
abstract class BoostByAiSearchBase extends ProcessorPluginBase implements PluginFormInterface {
  use PluginFormTrait;

  /**
   * Whether exact phrase search is supported by the index backend.
   *
   * @return bool
   *   True if the plugin supports exact phrase search.
   */
  protected function supportsExactPhraseSearch() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'search_api_ai_index' => '',
      'minimum_relevance_score' => 0.2,
      'number_to_return' => 10,
      'exact_phrase_action' => 'skip',
      'exact_phrase_action_reduce_number' => 2,
      'pass_conditions_fields' => [],
      // NULL means "inherit the backend's own default cap" (currently 10),
      // preserving existing behavior for sites that don't touch this.
      'max_pager_iterations' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState) {
    $form['search_api_ai_index'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Search Database to combine with'),
      '#description' => $this->t('Select the AI Search server to run the query against first in order to prepend top relevant results. Your AI Search index should index the same types of content and ideally be as close to an overlap of the same content indexed as possible. For best results index exactly the same content.'),
      '#options' => [],
      '#empty_option' => $this->t('- Select -'),
    ];
    $indexes = Index::loadMultiple();
    foreach ($indexes as $index) {
      if ($index->id() === $this->index->id()) {
        continue;
      }
      $server_id = $index->getServerId();
      if (!$server_id) {
        continue;
      }
      $server = Server::load($server_id);
      if (!$server instanceof ServerInterface || $server->getBackendId() !== 'search_api_ai_search') {
        continue;
      }
      $form['search_api_ai_index']['#options'][$index->id()] = $index->label() . '(' . $server->label() . ')';
    }
    $search_api_ai_index = $this->configuration['search_api_ai_index'] ?? $this->defaultConfiguration()['search_api_ai_index'];
    if (
      $search_api_ai_index
      && array_key_exists($search_api_ai_index, $form['search_api_ai_index']['#options'])
    ) {
      $form['search_api_ai_index']['#default_value'] = $search_api_ai_index;
    }

    $form['minimum_relevance_score'] = [
      '#type' => 'number',
      '#step' => 0.01,
      '#required' => TRUE,
      '#title' => $this->t('Minimum relevance score'),
      '#description' => $this->t('Only prepend results that have a score higher than this. The score should be between 0 and 1. 0 will return all results. 1 is almost impossible to achieve and will likely never return results. Between 0.2 and 0.5 are most likely to be useful.'),
      '#default_value' => $this->configuration['minimum_relevance_score'] ?? $this->defaultConfiguration()['minimum_relevance_score'],
    ];

    $form['number_to_return'] = [
      '#type' => 'number',
      '#step' => 1,
      '#required' => TRUE,
      '#title' => $this->t('Number of results to return'),
      '#description' => $this->t('The number of results to prepend. If found, up to this many results will be prepended to the SOLR search. Note that this is before filtering is applied, so you may wish to have a higher number here.'),
      '#default_value' => $this->configuration['number_to_return'] ?? $this->defaultConfiguration()['number_to_return'],
    ];

    $form['max_pager_iterations'] = [
      '#type' => 'number',
      '#step' => 1,
      '#min' => 0,
      '#required' => FALSE,
      '#title' => $this->t('Maximum retries when deduplicating results'),
      '#description' => $this->t('Every retry re-queries the AI Search backend for another batch, so this trades network round trips for a better chance of reaching the full "Number of results to return". Leave empty to use the backend default (currently 10). Set to 0 to fetch once and accept however many distinct results that yields without retrying — recommended for vector backends that only support a single ranked top-K query with no true pagination (e.g. Pinecone), where a retry cannot return new data and only adds latency.'),
      '#default_value' => $this->configuration['max_pager_iterations'] ?? $this->defaultConfiguration()['max_pager_iterations'],
    ];

    $field_options = [];
    foreach ($this->index->getFields() as $field_id => $field) {
      $field_options[$field_id] = $field->getLabel();
    }
    $form['pass_conditions_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Pass conditions from parent query'),
      '#description' => $this->t('Select which indexed fields from this index should have their top-level conditions copied to the AI Search query. This allows the AI Search to respect certain filters applied on the primary search (e.g. content type, status). Only simple top-level conditions are supported; nested condition groups are ignored.'),
      '#options' => $field_options,
      '#default_value' => $this->configuration['pass_conditions_fields'] ?? [],
    ];

    if ($this->supportsExactPhraseSearch()) {
      $form['exact_phrase_action'] = [
        '#type' => 'radios',
        '#required' => TRUE,
        '#title' => $this->t('Exact phrase action'),
        '#description' => $this->t('When the field to search with contains two quotes the user may be doing an exact search. AI Search is a representation of the data in vectors but does not contain the actual exact phrases. If an exact phrase search is run, skipping AI Search is recommended.'),
        '#default_value' => $this->configuration['exact_phrase_action'] ?? $this->defaultConfiguration()['exact_phrase_action'],
        '#options' => [
          'skip' => $this->t('Skip: When an exact phrase is searched (ie, sets of quotes found) do not perform a vector database search'),
          'reduce' => $this->t('Reduce: When an exact phrase is searched (ie, sets of quotes found) reduce the number of vector results to N results'),
          'continue' => $this->t('Continue: When an exact phrase is searched (ie, sets of quotes found), behave the same as if no quotes are found'),
        ],
      ];

      $form['exact_phrase_action_reduce_number'] = [
        '#type' => 'number',
        '#step' => 1,
        '#title' => $this->t('Number of results to return when an exact phrase is searched'),
        '#description' => $this->t('Instead of the full results, return a much smaller number of results. This is recommended to be 1 or 2 maximum so that exact matches are immediately visible.'),
        '#default_value' => $this->configuration['exact_phrase_action_reduce_number'] ?? $this->defaultConfiguration()['exact_phrase_action_reduce_number'],
        '#states' => [
          'visible' => [
            ':input[name="processors[database_boost_by_ai_search][settings][exact_phrase_action]"]' => ['value' => 'reduce'],
          ],
        ],
        '#attributes' => [
          'novalidate' => 'novalidate',
        ],
      ];
    }
    else {
      $form['exact_phrase_action'] = [
        '#type' => 'hidden',
        '#value' => 'continue',
      ];
      $form['exact_phrase_action_reduce_number'] = [
        '#type' => 'hidden',
        '#value' => '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

    // Server side validation only to avoid focus state issue.
    if (empty($form_state->getValue('search_api_ai_index'))) {
      $form_state->setErrorByName('search_api_ai_index', $this->t('Choose an AI Search index to search with.'));
    }

    if (
      $form_state->getValue('exact_phrase_action') === 'reduce'
      && !$form_state->getValue('exact_phrase_action_reduce_number') > 0
    ) {
      $form_state->setErrorByName('exact_phrase_action_reduce_number', $this->t('When the "Reduce" option is chosen, the number to reduce to must be greater than zero. Otherwise use the "Skip" option.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    if (isset($values['pass_conditions_fields'])) {
      $values['pass_conditions_fields'] = array_filter($values['pass_conditions_fields']);
      $form_state->setValues($values);
    }
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * Determine how many results to get.
   *
   * @param string|array $keywords
   *   The search terms.
   *
   * @return int
   *   The limit.
   */
  protected function determineLimit(string|array $keywords): int {
    if ($this->supportsExactPhraseSearch()) {
      if (!is_array($keywords)) {
        $keywords = [$keywords];
      }
      foreach ($keywords as $keyword) {

        // If there are at least 2 quotations, follow the exact phrase action
        // that the site builder selected.
        if (substr_count($keyword, '"') >= 2) {
          switch ($this->configuration['exact_phrase_action']) {
            case 'skip':
              return 0;

            case 'reduce':
              return (int) $this->configuration['exact_phrase_action_reduce_number'];

            case 'continue':
              return (int) $this->configuration['number_to_return'];
          }
        }
      }
    }
    return (int) $this->configuration['number_to_return'];
  }

  /**
   * Run the AI search and return results.
   *
   * @param string|array $keywords
   *   The keyword string or array of keywords for the search.
   * @param \Drupal\search_api\Query\QueryInterface $parent_query
   *   The parent query from, for example, the Solr or Database server.
   *
   * @return array
   *   An array of results with Drupal entity IDs as keys.
   */
  protected function getAiSearchResults(string|array $keywords, ?QueryInterface $parent_query = NULL): array {
    // Number of results to return from AI search.
    $limit = $this->determineLimit($keywords);
    if ($limit <= 0) {
      return [];
    }

    // Perform the query against the AI index.
    try {
      $ai_index = Index::load($this->configuration['search_api_ai_index']);
      if (!$ai_index) {
        throw new \Exception('AI Search index could not be loaded.');
      }

      // Run the search with the given limit.
      // @todo check if there is an exact phrase search requested.
      /** @var \Drupal\search_api\Query\QueryInterface $query */
      $query = $ai_index->query([
        'limit' => $limit,
      ]);
      $query->setOption('search_api_bypass_access', TRUE);
      // Only override the backend's own retry cap if the site builder
      // configured one; NULL means "leave the backend default alone".
      $max_pager_iterations = $this->configuration['max_pager_iterations'] ?? NULL;
      if ($max_pager_iterations !== NULL && $max_pager_iterations !== '') {
        $query->setOption('search_api_ai_max_pager_iterations', (int) $max_pager_iterations);
      }
      $query->keys($keywords);

      // Pass on the parent query options into the AI Search query.
      if ($parent_query instanceof QueryInterface) {
        $parent_query_options = $parent_query->getOptions();
        $query->setOption('parent_query_options', $parent_query_options);
        $this->applyParentConditions($query, $parent_query);
      }

      $results = $query->execute();

      // Return the entity IDs from the results.
      $ai_entity_ids = [];
      foreach ($results->getResultItems() as $result_item) {
        if ($result_item->getScore() < $this->configuration['minimum_relevance_score']) {
          continue;
        }
        $ai_entity_ids[$result_item->getId()] = $result_item->getScore();
      }

      return $ai_entity_ids;
    }
    catch (\Exception $exception) {
      /** @var \Psr\Log\LoggerInterface $logger */
      // @phpstan-ignore-next-line
      $logger = \Drupal::logger('ai_search');
      $logger->warning('Failed to run AI search: @message', [
        '@message' => $exception->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Copy configured field conditions from the parent query to the AI query.
   *
   * Only simple top-level conditions on fields selected by the site builder
   * are copied. Nested condition groups are intentionally skipped because the
   * AI Search backend supports only a limited subset of condition types.
   *
   * @param \Drupal\search_api\Query\QueryInterface $ai_query
   *   The AI Search query being built.
   * @param \Drupal\search_api\Query\QueryInterface $parent_query
   *   The parent (Solr or Database) query whose conditions to inspect.
   */
  protected function applyParentConditions(QueryInterface $ai_query, QueryInterface $parent_query): void {
    $configured_fields = array_filter($this->configuration['pass_conditions_fields'] ?? []);
    if (empty($configured_fields)) {
      return;
    }
    $this->applyConditionsFromGroup($ai_query, $parent_query->getConditionGroup(), $configured_fields);
  }

  /**
   * Recursively copies leaf conditions from a group to the AI query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $ai_query
   *   The AI Search query being built.
   * @param \Drupal\search_api\Query\ConditionGroupInterface $group
   *   The condition group to inspect.
   * @param array $configured_fields
   *   Allowed list of field IDs whose conditions should be forwarded.
   */
  private function applyConditionsFromGroup(QueryInterface $ai_query, ConditionGroupInterface $group, array $configured_fields): void {
    foreach ($group->getConditions() as $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $this->applyConditionsFromGroup($ai_query, $condition, $configured_fields);
      }
      elseif ($condition instanceof ConditionInterface && isset($configured_fields[$condition->getField()])) {
        $ai_query->addCondition($condition->getField(), $condition->getValue(), $condition->getOperator());
      }
    }
  }

}
