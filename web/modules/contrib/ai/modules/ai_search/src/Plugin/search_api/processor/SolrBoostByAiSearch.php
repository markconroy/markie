<?php

namespace Drupal\ai_search\Plugin\search_api\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;

/**
 * Prepend AI Search results into the database search..
 *
 * @SearchApiProcessor(
 *   id = "solr_boost_by_ai_search",
 *   label = @Translation("Boost SOLR by AI Search"),
 *   description = @Translation("Prepend results from the AI Search into the SOLR results ready for subsequent filtering (if any) to improve relevance."),
 *   stages = {
 *     "preprocess_query" = 0,
 *   }
 * )
 */
class SolrBoostByAiSearch extends BoostByAiSearchBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    if ($index->getServerInstance()->getBackendId() == 'search_api_solr') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function supportsExactPhraseSearch() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);

    if ($this->index->getServerId() && $server = Server::load($this->index->getServerId())) {
      if ($server->getBackendId() !== 'search_api_solr') {
        $form_state->setErrorByName('search_api_ai_index', $this->t('This processor plugin only supports "search_api_solr", but the backend of this index is "@backend"', [
          '@backend' => $server->getBackendId(),
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessSearchQuery(QueryInterface $query) {
    parent::preprocessSearchQuery($query);

    // Only do something if we have search terms. It is possible that the
    // index is being filtered only without any terms, in which case we have
    // nothing more to do.
    if ($query_string_keys = $query->getKeys()) {
      $ai_results = $this->getAiSearchResults($query_string_keys);
      if ($ai_results) {

        // This gets passed via the SolrBoostByAiSearchEventSubscriber class
        // back to the queryAlter() method.
        $query->setOption('ai_search_ids', $ai_results);
      }
    }
  }

  /**
   * Alter the SOLR Query to elevate specific IDs.
   *
   * @param \Solarium\Core\Query\QueryInterface $solarium_query
   *   The SOLR query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API query.
   */
  public static function queryAlter(SolariumQueryInterface $solarium_query, QueryInterface $query): void {
    $ai_search_ids = $query->getOption('ai_search_ids');
    if (empty($ai_search_ids)) {
      return;
    }

    // The item IDs are stored with a prefix that includes the server and index
    // ID, followed by the item ID.
    $index = $query->getIndex();
    $hash = $index->getServerInstance()->getBackend()->getTargetedSiteHash($index);
    $prefix = $hash . '-' . $index->id() . '-';

    // Build the parameter string and set to elevate these IDs.
    // The elevate IDs option respects the order provided, so the results
    // will therefore respect the order provided by the AI Search relevance.
    $param_parts = [];
    foreach (array_keys($ai_search_ids) as $ai_search_id) {
      $param_parts[] = $prefix . $ai_search_id;
    }
    $solarium_query->addParam('elevateIds', implode(',', $param_parts));
  }

}
