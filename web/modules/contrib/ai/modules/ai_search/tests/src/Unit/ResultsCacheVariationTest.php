<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_search\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_search\Enum\ResultsCacheMode;
use Drupal\ai_search\ResultsCacheVariation;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use Drupal\search_api\Query\Query;
use Drupal\search_api\ServerInterface;
use Drupal\views\ViewExecutable;

/**
 * Tests how cached AI Search results are varied between users.
 *
 * @coversDefaultClass \Drupal\ai_search\ResultsCacheVariation
 * @group ai_search
 */
class ResultsCacheVariationTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Merging cache contexts validates them through the container.
    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Builds the service for an index configured with the given mode.
   *
   * @param string|null $mode
   *   The stored results_cache_mode value, or NULL for none stored.
   * @param int $user_id
   *   The current user ID.
   * @param string[] $roles
   *   The current user's roles.
   *
   * @return \Drupal\ai_search\ResultsCacheVariation
   *   The service.
   */
  private function buildService(?string $mode, int $user_id = 5, array $roles = ['authenticated', 'editor']): ResultsCacheVariation {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['index_id', 'test_index'],
      ['results_cache_mode', $mode],
    ]);
    $config->method('getCacheTags')->willReturn(['config:ai_search.index.test_index']);
    $config->method('getCacheContexts')->willReturn([]);
    $config->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('ai_search.index.test_index')->willReturn($config);
    $current_user = $this->createMock(AccountProxyInterface::class);
    $current_user->method('id')->willReturn($user_id);
    $current_user->method('getRoles')->willReturn($roles);
    return new ResultsCacheVariation($config_factory, $current_user);
  }

  /**
   * Builds an enabled index mock on a server with the given backend.
   *
   * @param string $backend_id
   *   The server backend ID.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index mock.
   */
  private function buildIndex(string $backend_id = 'search_api_ai_search'): IndexInterface {
    $server = $this->createMock(ServerInterface::class);
    $server->method('getBackendId')->willReturn($backend_id);
    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('test_index');
    $index->method('status')->willReturn(TRUE);
    $index->method('hasValidServer')->willReturn(TRUE);
    $index->method('getServerInstance')->willReturn($server);
    $index->method('getDatasources')->willReturn([]);
    $index->method('getCacheTagsToInvalidate')->willReturn(['config:search_api.index.test_index']);
    return $index;
  }

  /**
   * Builds a query on the given index, altered by the given service.
   *
   * @param \Drupal\ai_search\ResultsCacheVariation $service
   *   The service.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   The index, or NULL for an AI Search index.
   * @param array $options
   *   The query options.
   *
   * @return \Drupal\search_api\Query\Query
   *   The altered query.
   */
  private function alterQuery(ResultsCacheVariation $service, ?IndexInterface $index = NULL, array $options = []): Query {
    $query = new Query($index ?? $this->buildIndex(), $options);
    $service->alterQuery($query);
    return $query;
  }

  /**
   * Tests that per user mode gives each user their own results cache key.
   *
   * @covers ::alterQuery
   */
  public function testPerUserVariesByUser(): void {
    $first_query = $this->alterQuery($this->buildService('per_user', 5));
    $second_query = $this->alterQuery($this->buildService('per_user', 6));

    $this->assertSame('user:5', $first_query->getOption(ResultsCacheVariation::QUERY_OPTION));
    $this->assertNotSame((string) $first_query, (string) $second_query, 'Different users must not share a results cache key.');
    $this->assertContains('user', $first_query->getCacheContexts());
    $this->assertSame(Cache::PERMANENT, $first_query->getCacheMaxAge());
  }

  /**
   * Tests that per role mode shares results between users with equal roles.
   *
   * @covers ::alterQuery
   */
  public function testPerRoleVariesByRolesOnly(): void {
    $editor_query = $this->alterQuery($this->buildService('per_role', 5, ['editor', 'authenticated']));
    $other_editor_query = $this->alterQuery($this->buildService('per_role', 6, ['authenticated', 'editor']));
    $anonymous_query = $this->alterQuery($this->buildService('per_role', 0, ['anonymous']));

    $this->assertSame('roles:authenticated,editor', $editor_query->getOption(ResultsCacheVariation::QUERY_OPTION));
    $this->assertSame((string) $editor_query, (string) $other_editor_query, 'Users with the same roles must share a results cache key, whatever order the roles are in.');
    $this->assertNotSame((string) $editor_query, (string) $anonymous_query, 'Users with different roles must not share a results cache key.');
    $this->assertContains('user.roles', $editor_query->getCacheContexts());
  }

  /**
   * Tests that per role mode is used when no mode is stored for the index.
   *
   * @covers ::alterQuery
   * @covers ::getMode
   */
  public function testMissingModeDefaultsToPerRole(): void {
    $service = $this->buildService(NULL);

    $this->assertSame(ResultsCacheMode::PerRole, $service->getMode('test_index'));
    $this->assertSame('roles:authenticated,editor', $this->alterQuery($service)->getOption(ResultsCacheVariation::QUERY_OPTION));
  }

  /**
   * Tests that "do not cache" mode makes the results uncacheable.
   *
   * @covers ::alterQuery
   */
  public function testNoneDisablesCaching(): void {
    $first_query = $this->alterQuery($this->buildService('none', 5));
    $second_query = $this->alterQuery($this->buildService('none', 6));

    $this->assertSame(0, $first_query->getCacheMaxAge());
    // Cache plugins ignoring the max-age must still not share results.
    $this->assertSame('user:5', $first_query->getOption(ResultsCacheVariation::QUERY_OPTION));
    $this->assertNotSame((string) $first_query, (string) $second_query);
    $this->assertContains('user', $first_query->getCacheContexts());
  }

  /**
   * Tests that the shared mode leaves the query untouched.
   *
   * @covers ::alterQuery
   */
  public function testSharedLeavesQueryUntouched(): void {
    $first_query = $this->alterQuery($this->buildService('shared', 5));
    $second_query = $this->alterQuery($this->buildService('shared', 0, ['anonymous']));

    $this->assertNull($first_query->getOption(ResultsCacheVariation::QUERY_OPTION));
    $this->assertSame((string) $first_query, (string) $second_query);
    $this->assertSame(Cache::PERMANENT, $first_query->getCacheMaxAge());
  }

  /**
   * Tests that searches bypassing access checks are not varied.
   *
   * @covers ::alterQuery
   */
  public function testBypassAccessIsNotVaried(): void {
    $query = $this->alterQuery($this->buildService('none'), NULL, ['search_api_bypass_access' => TRUE]);

    $this->assertSame(Cache::PERMANENT, $query->getCacheMaxAge());
    $this->assertNull($query->getOption(ResultsCacheVariation::QUERY_OPTION));
  }

  /**
   * Tests that indexes on other backends are not varied.
   *
   * @covers ::alterQuery
   */
  public function testOtherBackendsAreNotVaried(): void {
    $query = $this->alterQuery($this->buildService('none'), $this->buildIndex('search_api_db'));

    $this->assertSame(Cache::PERMANENT, $query->getCacheMaxAge());
    $this->assertNull($query->getOption(ResultsCacheVariation::QUERY_OPTION));
  }

  /**
   * Tests that the query depends on the index's AI Search config.
   *
   * @covers ::alterQuery
   */
  public function testQueryDependsOnIndexConfig(): void {
    $query = $this->alterQuery($this->buildService('shared'));

    $this->assertContains('config:ai_search.index.test_index', $query->getCacheTags(), 'Changing the mode must invalidate results cached under the previous mode.');
  }

  /**
   * Tests that the query's cacheability is applied to the rendered view.
   *
   * @covers ::alterViewCacheability
   */
  public function testViewRenderArrayReceivesQueryCacheability(): void {
    $search_api_query = $this->alterQuery($this->buildService('per_user'));
    $search_api_query->mergeCacheMaxAge(300);
    $views_query = $this->createMock(SearchApiQuery::class);
    $views_query->method('getSearchApiQuery')->willReturn($search_api_query);
    $view = $this->createMock(ViewExecutable::class);
    $view->method('getQuery')->willReturn($views_query);
    $view->element = [
      '#cache' => [
        'contexts' => ['url'],
        'max-age' => Cache::PERMANENT,
      ],
    ];

    $this->buildService('per_user')->alterViewCacheability($view);

    $this->assertContains('url', $view->element['#cache']['contexts']);
    $this->assertContains('user', $view->element['#cache']['contexts']);
    $this->assertSame(300, $view->element['#cache']['max-age']);
  }

}
