<?php

declare(strict_types=1);

namespace Drupal\ai_search;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ai_search\Enum\ResultsCacheMode;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\Query\QueryInterface;
use Drupal\views\ViewExecutable;

/**
 * Varies cached AI Search results according to the index's cache mode.
 *
 * The AI Search backend checks access per result for the current user, which
 * nothing in the query reflects. The Search API results cache key is built
 * from the query after hook_search_api_query_alter() has run but before the
 * backend searches, so the variation has to be added to the query here rather
 * than in the backend.
 *
 * @internal
 */
class ResultsCacheVariation {

  /**
   * The query option holding the cache variation, part of the results key.
   */
  public const QUERY_OPTION = 'ai_search_results_cache_variation';

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * Checks whether an index is stored on an AI Search server.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index.
   *
   * @return bool
   *   TRUE if the index uses the AI Search backend.
   */
  public function isAiSearchIndex(IndexInterface $index): bool {
    return $index->hasValidServer()
      && $index->getServerInstance()->getBackendId() === 'search_api_ai_search';
  }

  /**
   * Gets the results cache mode configured for an index.
   *
   * @param string $index_id
   *   The search index ID.
   *
   * @return \Drupal\ai_search\Enum\ResultsCacheMode
   *   The configured mode, or the default if none is configured.
   */
  public function getMode(string $index_id): ResultsCacheMode {
    return ResultsCacheMode::fromStoredValue(
      $this->configFactory->get('ai_search.index.' . $index_id)->get('results_cache_mode')
    );
  }

  /**
   * Adds the cache variation for the index's cache mode to a search query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The search query, before it is executed.
   */
  public function alterQuery(QueryInterface $query): void {
    // Without access checks, the results are the same for everyone.
    if ($query->getOption('search_api_bypass_access')) {
      return;
    }
    $index = $query->getIndex();
    if (!$query instanceof RefinableCacheableDependencyInterface || !$this->isAiSearchIndex($index)) {
      return;
    }

    // Changing the mode must invalidate results cached under the previous one,
    // including when the config is changed outside the index form.
    $config = $this->configFactory->get('ai_search.index.' . $index->id());
    $query->addCacheableDependency($config);

    switch (ResultsCacheMode::fromStoredValue($config->get('results_cache_mode'))) {
      case ResultsCacheMode::None:
        $query->mergeCacheMaxAge(0);
        // A cache plugin that ignores the query's max-age would still store
        // results. Varying by user means those are at worst cached per user
        // rather than shared.
        $query->setOption(self::QUERY_OPTION, 'user:' . $this->currentUser->id());
        $query->addCacheContexts(['user']);
        break;

      case ResultsCacheMode::PerUser:
        $query->setOption(self::QUERY_OPTION, 'user:' . $this->currentUser->id());
        $query->addCacheContexts(['user']);
        break;

      case ResultsCacheMode::PerRole:
        $roles = $this->currentUser->getRoles();
        sort($roles);
        $query->setOption(self::QUERY_OPTION, 'roles:' . implode(',', $roles));
        $query->addCacheContexts(['user.roles']);
        break;

      case ResultsCacheMode::Shared:
        break;
    }
  }

  /**
   * Applies the search query's cacheability to a view's render array.
   *
   * Search API does not pass the cache contexts and max-age set on a query at
   * runtime on to the rendered view, so without this the rendered output would
   * still be shared between users that the results cache keeps apart.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view about to be rendered.
   */
  public function alterViewCacheability(ViewExecutable $view): void {
    $views_query = $view->getQuery();
    if (!$views_query instanceof SearchApiQuery) {
      return;
    }
    $query = $views_query->getSearchApiQuery();
    if (!$query instanceof CacheableDependencyInterface || !$this->isAiSearchIndex($query->getIndex())) {
      return;
    }
    CacheableMetadata::createFromRenderArray($view->element)
      ->addCacheableDependency($query)
      ->applyTo($view->element);
  }

}
