<?php

namespace Drupal\Tests\ai_search\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ai_search_post_update_results_cache_mode().
 *
 * @group ai_search
 */
#[RunTestsInSeparateProcesses]
class ResultsCacheModePostUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'search_api', 'ai', 'ai_search'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Load the post update file so the update function is available.
    include_once dirname(__DIR__, 4) . '/ai_search.post_update.php';
    $config_factory = \Drupal::configFactory();
    $config_factory->getEditable('search_api.server.ai_server')
      ->set('id', 'ai_server')
      ->set('backend', 'search_api_ai_search')
      ->save();
    $config_factory->getEditable('search_api.server.db_server')
      ->set('id', 'db_server')
      ->set('backend', 'search_api_db')
      ->save();
  }

  /**
   * Writes the raw config for a search index.
   *
   * @param string $index_id
   *   The index ID.
   * @param string $server_id
   *   The server ID.
   * @param string[] $processor_ids
   *   The enabled processor IDs.
   */
  private function createIndexConfig(string $index_id, string $server_id, array $processor_ids = []): void {
    \Drupal::configFactory()->getEditable('search_api.index.' . $index_id)
      ->set('id', $index_id)
      ->set('name', 'Index ' . $index_id)
      ->set('server', $server_id)
      ->set('processor_settings', array_fill_keys($processor_ids, []))
      ->save();
  }

  /**
   * Gets the stored results cache mode for an index.
   *
   * @param string $index_id
   *   The index ID.
   *
   * @return string|null
   *   The stored mode, if any.
   */
  private function getStoredMode(string $index_id): ?string {
    return \Drupal::config('ai_search.index.' . $index_id)->get('results_cache_mode');
  }

  /**
   * Tests the mode chosen for each kind of index.
   *
   * @covers ::ai_search_post_update_results_cache_mode
   */
  public function testSetsModeFromAccessProcessors(): void {
    $this->createIndexConfig('content_access_index', 'ai_server', ['content_access']);
    $this->createIndexConfig('both_index', 'ai_server', ['content_access', 'role_access']);
    $this->createIndexConfig('role_access_index', 'ai_server', ['role_access']);
    $this->createIndexConfig('plain_index', 'ai_server');
    $this->createIndexConfig('db_index', 'db_server', ['content_access']);
    $cache = \Drupal::cache();
    $cache->set('plain_index_results', 'cached', tags: ['search_api_list:plain_index']);
    $cache->set('db_index_results', 'cached', tags: ['search_api_list:db_index']);

    $message = ai_search_post_update_results_cache_mode();

    $this->assertFalse($cache->get('plain_index_results'), 'Results cached before the update must be invalidated.');
    $this->assertNotFalse($cache->get('db_index_results'), 'Results of indexes on other backends must be left alone.');

    $this->assertSame('per_user', $this->getStoredMode('content_access_index'));
    $this->assertSame('per_user', $this->getStoredMode('both_index'), 'Content access must win when both processors are enabled.');
    $this->assertSame('per_role', $this->getStoredMode('role_access_index'));
    $this->assertSame('per_role', $this->getStoredMode('plain_index'));
    $this->assertNull($this->getStoredMode('db_index'), 'Indexes on other backends must be left alone.');
    $this->assertSame('plain_index', \Drupal::config('ai_search.index.plain_index')->get('index_id'));

    $this->assertStringContainsString('Set the results caching mode on 4 AI Search index(es).', $message);
    $this->assertStringContainsString('Index plain_index', $message, 'Indexes defaulted to per role without an access processor must be warned about.');
    $this->assertStringNotContainsString('Index role_access_index', $message);
    $this->assertStringNotContainsString('Index content_access_index', $message);
  }

  /**
   * Tests that an index which already has a mode is left untouched.
   *
   * @covers ::ai_search_post_update_results_cache_mode
   */
  public function testDoesNotOverrideExistingMode(): void {
    $this->createIndexConfig('configured_index', 'ai_server', ['content_access']);
    \Drupal::configFactory()->getEditable('ai_search.index.configured_index')
      ->set('index_id', 'configured_index')
      ->set('results_cache_mode', 'none')
      ->save();

    ai_search_post_update_results_cache_mode();

    $this->assertSame('none', $this->getStoredMode('configured_index'));
  }

}
