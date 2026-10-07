<?php

namespace Drupal\Tests\ai_search\Functional;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\BrowserTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Contains AI Search UI setup functional tests.
 *
 * @group ai_search_functional
 */
#[RunTestsInSeparateProcesses]
class AiSearchSetupMySqlTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_search',
    'test_ai_provider_mysql',
    'test_ai_vdb_provider_mysql',
    'node',
    'taxonomy',
    'user',
    'system',
    'field_ui',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Nodes for testing the indexing.
   *
   * @var array
   *   An array of nodes for testing.
   */
  protected $nodes = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {

    // Allow skipping the test if FFI is not loaded AND PHP version is not
    // 8.3. We must update gitlab-ci.yml and this with 8.4, etc as versions
    // change. See docs/modules/ai_search/index.md for details.
    if (
      (!in_array('FFI', get_loaded_extensions()) || !class_exists('FFI'))
      && !str_starts_with(phpversion(), '8.3')
      && !str_starts_with(phpversion(), '8.4')
    ) {
      $this->markTestSkipped('FFI extension is not loaded.');
    }
    parent::setUp();

    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->adminUser = $this->drupalCreateUser([
      'access administration pages',
      'administer content types',
      'access content overview',
      'administer nodes',
      'administer node fields',
      'bypass node access',
      'administer ai',
      'administer ai providers',
      'administer search_api',
      'administer views',
    ]);

    $this->setupServerAndIndex();
    $this->createSampleContent();
    $this->indexContent();
  }

  /**
   * Set up server and index.
   *
   * This both tests the setup and provides a default setup for other tests.
   */
  public function setupServerAndIndex(): void {
    $this->drupalLogin($this->adminUser);

    // Set the embedding default provider as the test MySQL one.
    $this->drupalGet('admin/config/ai/settings');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
    ], 'Choose Model');
    $this->submitForm([
      'operation__embeddings' => 'test_mysql_provider',
      'model__embeddings' => 'mysql',
    ], 'Save configuration');

    // Set up Search API Server.
    $this->drupalGet('admin/config/search/search-api/add-server');
    $this->submitForm([
      'name' => 'Test MySQL AI Vector Database',
      'id' => 'test_mysql_vdb',
      'backend' => 'search_api_ai_search',
      'status' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[embeddings_engine]' => 'test_mysql_provider__mysql',
      'backend_config[database]' => 'test_mysql',
      'backend_config[embeddings_engine_configuration][dimensions]' => 384,
      'backend_config[embeddings_engine_configuration][set_dimensions]' => TRUE,
      'backend_config[include_raw_embedding_vector]' => TRUE,
    ], 'Save');
    $this->submitForm([
      'backend_config[database_settings][database_name]' => 'test_mysql_database',
      'backend_config[database_settings][collection]' => 'test_mysql_collection',
    ], 'Save');

    // Set up index.
    $this->drupalGet('admin/config/search/search-api/add-index');
    $this->submitForm([
      'name' => 'Test MySQL VDB Index',
      'id' => 'test_mysql_vdb_index',
      'datasources[entity:node]' => TRUE,
      'server' => 'test_mysql_vdb',
      'options[cron_limit]' => 5,
    ], 'Save and add fields');
    $this->submitForm([], 'Save and add fields');

    // Add fields.
    $page = $this->getSession()->getPage();
    // Rendered html.
    $page->pressButton('rendered_item');
    $this->submitForm([
      'view_mode[entity:node][:default]' => 'default',
    ], 'Save');
    // Title.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields/add/nojs');
    $page->pressButton('entity:node/title');
    // Done.
    $page->clickLink('edit-done');

    // Selecting indexing options on fields page.
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');

    // Check indexing options have been configured.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->pageTextContains('Indexing options have been configured.');
  }

  /**
   * Create sample content to check index.
   */
  public function createSampleContent(): void {
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Chocolate Cake',
      'field_body' => [
        'value' => 'A delicious chocolate dessert made with cocoa powder and dark chocolate.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Strawberry Cheese Cake',
      'field_body' => [
        'value' => 'A sweet cheese based dessert make with strawberries on a pie-like crust.',
        'format' => 'plain_text',
      ],
      'status' => 0,
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Vanilla Ice Cream',
      'field_body' => [
        'value' => 'A creamy vanilla dessert made with milk, cream, and vanilla extract.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Tomato Soup',
      'field_body' => [
        'value' => 'A warm starter made with fresh tomatoes, garlic, and basil.',
        'format' => 'plain_text',
      ],
    ]);
    $this->nodes[] = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Grilled Chicken Breast',
      'field_body' => [
        'value' => 'A savory main course made with marinated chicken breast, grilled to perfection.',
        'format' => 'plain_text',
      ],
    ]);
  }

  /**
   * Index content.
   */
  public function indexContent(): void {
    $cron_service = \Drupal::service('cron');
    $cron_service->run();
  }

  /**
   * Test the content indexing has completed.
   */
  public function testContentIndexingCompleted(): void {
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->elementTextContains('css', '.progress__percentage', '100%');
  }

  /**
   * Test the field main and contextual indexing options.
   */
  public function testFieldIndexingOptions() {
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'checker[entity]' => $this->nodes[0]->label() . ' (' . $this->nodes[0]->id() . ')',
    ], 'Save changes');

    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $this->assertSession()->pageTextContains('Chocolate Cake');
    }
    else {
      $has_markdown_link = $this->getSession()->getPage()->hasContent('[Chocolate Cake](' . $this->nodes[0]->toUrl()->toString() . ')');
      $has_markdown_title = $this->getSession()->getPage()->hasContent('# Chocolate Cake');
      $this->assertTrue($has_markdown_link || $has_markdown_title);
    }

    $this->assertSession()->pageTextContains('Title: Chocolate Cake');

    // Ignore the title and expect it to no longer show up.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'ignore',
    ], 'Save changes');
    $this->submitForm([
      'checker[entity]' => $this->nodes[0]->label() . ' (' . $this->nodes[0]->id() . ')',
    ], 'Save changes');

    if (class_exists('League\CommonMark\CommonMarkConverter')) {
      $this->assertSession()->pageTextContains('Chocolate Cake');
    }
    else {
      $has_markdown_link = $this->getSession()->getPage()->hasContent('[Chocolate Cake](' . $this->nodes[0]->toUrl()->toString() . ')');
      $has_markdown_title = $this->getSession()->getPage()->hasContent('# Chocolate Cake');
      $this->assertTrue($has_markdown_link || $has_markdown_title);
    }

    $this->assertSession()->pageTextNotContains('Title: Chocolate Cake');

    // Reset in case parallel test run.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index/fields');
    $this->submitForm([
      'fields[rendered_item][indexing_option]' => 'main_content',
      'fields[title][indexing_option]' => 'contextual_content',
    ], 'Save changes');
  }

  /**
   * Test searching via a search view.
   */
  public function testSearchView() {
    // Create the view using our index.
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm([
      'label' => 'Test search view',
      'id' => 'test_search_view',
      'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
      'page[title]' => 'Test Search View',
      'page[create]' => 1,
      'page[path]' => 'test-search-view',
    ], 'Save and edit');

    // Add a search exposed filter.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/filter');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
    ], 'Add and configure filter criteria');

    // Expose the filter then save it.
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
    ];
    $this->submitForm($edit, 'Expose filter');
    $edit = [
      'options[expose_button][checkbox][checkbox]' => 1,
      'options[group_button][radios][radios]' => 0,
    ];
    $this->submitForm($edit, 'Apply');
    $this->submitForm([], 'Save');

    // Sort by relevance.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test_search_view/default/sort');
    $this->submitForm([
      'name[search_api_index_test_mysql_vdb_index.search_api_relevance]' => 'search_api_index_test_mysql_vdb_index.search_api_relevance',
    ], 'Add and configure sort criteria');
    $this->submitForm([
      'options[order]' => 'DESC',
    ], 'Apply');
    $this->submitForm([], 'Save');

    // Remove sort by authored on.
    $this->drupalGet('admin/structure/views/nojs/handler/test_search_view/default/sort/created');
    $this->submitForm([], 'Remove');
    $this->submitForm([], 'Save');

    // Check results when logged in.
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry',
    ], 'Apply');
    $rows = $this->cssSelect('.views-row');
    $this->assertStringContainsString('Strawberry Cheese Cake', $rows[0]->getText(), 'Row 1 contains "cake".');

    // Now logged out: Ensure the unpublished item does not exist.
    $this->drupalLogout();
    $this->drupalGet('test-search-view');
    $this->submitForm([
      'search_api_fulltext' => 'Strawberry',
    ], 'Apply');
    $rows = $this->cssSelect('.views-row');
    $this->assertSession()->pageTextNotContains('Strawberry Cheese Cake');
  }

  /**
   * Tests the chunked indexing mechanism for a single, very large item.
   *
   * Creates a node whose rendered output exceeds the per-batch chunk limit
   * (10 chunks) and verifies that successive cron runs incrementally process
   * it until fully indexed.
   *
   * @see \Drupal\ai_search\Plugin\EmbeddingStrategy\LongChunkEmbeddingBase
   * @see \Drupal\ai_search\Utility\AiSearchIndexingBatchHelper::process()
   */
  public function testChunkedIndexing(): void {
    $this->drupalLogin($this->adminUser);

    // Ensure the existing content is fully indexed.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->elementTextContains('css', '.progress__percentage', '100%');

    // Ensure the body field exists on article so the rendered_item is long.
    $this->addBodyFieldToArticle();

    // str_repeat produces ~54,000 characters → well over 10 chunks.
    $long_text = str_repeat('This is a very long piece of text designed to test the chunking mechanism. Each sentence adds more content to be processed. ', 500);
    $long_node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Very Long Article for Chunking Test',
      'body' => ['value' => $long_text, 'format' => 'plain_text'],
      'status' => 0,
    ]);
    $long_node_item_id = 'entity:node/' . $long_node->id() . ':en';

    $cron = \Drupal::service('cron');
    $db = \Drupal::database();

    // First cron run — starts processing the large item.
    $cron->run();

    $status = $db->select('search_api_item', 'sai')
      ->fields('sai', ['processed_chunks', 'total_chunks'])
      ->condition('index_id', 'test_mysql_vdb_index')
      ->condition('item_id', $long_node_item_id)
      ->execute()
      ->fetchAssoc();

    $this->assertNotNull($status, 'Long node has a tracking row after the first cron run.');
    $total_chunks = (int) $status['total_chunks'];
    $processed_run_1 = (int) $status['processed_chunks'];

    $this->assertGreaterThan(10, $total_chunks,
      sprintf('Long node produced %d chunks (expected > 10).', $total_chunks));
    $this->assertEquals(10, $processed_run_1,
      sprintf('First cron run processed %d chunks (expected 10).', $processed_run_1));

    // Second cron run — processes the next batch.
    $cron->run();

    $processed_run_2 = (int) $db->select('search_api_item', 'sai')
      ->fields('sai', ['processed_chunks'])
      ->condition('index_id', 'test_mysql_vdb_index')
      ->condition('item_id', $long_node_item_id)
      ->execute()
      ->fetchField();

    $expected_run_2 = min($total_chunks, 20);
    $this->assertEquals($expected_run_2, $processed_run_2,
      sprintf('Second cron run processed %d chunks (expected %d).', $processed_run_2, $expected_run_2));

    // Keep running cron until fully indexed (with a safety ceiling).
    $max_runs = (int) ceil($total_chunks / 10) + 2;
    $processed = $processed_run_2;
    $runs = 2;
    while ($processed < $total_chunks && $runs < $max_runs) {
      $cron->run();
      $runs++;
      $processed = (int) $db->select('search_api_item', 'sai')
        ->fields('sai', ['processed_chunks'])
        ->condition('index_id', 'test_mysql_vdb_index')
        ->condition('item_id', $long_node_item_id)
        ->execute()
        ->fetchField();
    }

    $this->assertLessThan($max_runs, $runs,
      'Indexing finished within the expected number of cron runs.');
    $this->assertEquals($total_chunks, $processed,
      sprintf('Item fully processed after %d runs (%d/%d chunks).', $runs, $processed, $total_chunks));

    // UI should reflect 100% for all items.
    $this->drupalGet('admin/config/search/search-api/index/test_mysql_vdb_index');
    $this->assertSession()->elementTextContains('css', '.progress__percentage', '100%');
  }

  /**
   * Tests that chunks from previous cron runs are not deleted on resume.
   *
   * Verifies that in-progress items skip the delete step so chunks written in
   * batch N are still present in the vector store when batch N+1 runs. This
   * exercises the setSkipDeleteItemIds() progressive-enhancement path.
   *
   * The test counts *distinct* chunk IDs (drupal_long_id) rather than raw map
   * rows, because the test VDB provider inserts one row per embedding dimension
   * value rather than one row per chunk.
   *
   * @see \Drupal\ai\Base\AiVdbProviderClientBase::setSkipDeleteItemIds()
   * @see \Drupal\ai_search\Plugin\search_api\backend\SearchApiAiSearchBackend::indexItemsWithChunkSlicing()
   */
  public function testChunkedIndexingPreservesChunksBetweenRuns(): void {
    $this->drupalLogin($this->adminUser);

    $this->addBodyFieldToArticle();

    $long_text = str_repeat('Chunk preservation test content that must exceed ten chunks per item. ', 500);
    $long_node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Chunk Preservation Test Node',
      'body' => ['value' => $long_text, 'format' => 'plain_text'],
      'status' => 0,
    ]);
    $item_id = 'entity:node/' . $long_node->id() . ':en';

    $cron = \Drupal::service('cron');
    $db = \Drupal::database();
    $map_table = 'test_mysql_database_test_mysql_collection_map';

    // Run cron once — first 10 chunks should be stored.
    $cron->run();

    // Count distinct chunk IDs (drupal_long_id) for this item after run 1.
    // Each chunk has a unique ID like 'entity:node/6:en:0', ':en:1', etc.
    $chunk_ids_run_1 = $db->select($map_table, 'm')
      ->fields('m', ['drupal_long_id'])
      ->distinct()
      ->condition('drupal_entity_id', $item_id)
      ->execute()
      ->fetchCol();

    $this->assertCount(10, $chunk_ids_run_1,
      sprintf('After run 1, expected 10 distinct chunk IDs, got %d.', count($chunk_ids_run_1)));

    // Run cron again — skip-delete must keep run-1 chunk IDs intact.
    $cron->run();

    $chunk_ids_run_2 = $db->select($map_table, 'm')
      ->fields('m', ['drupal_long_id'])
      ->distinct()
      ->condition('drupal_entity_id', $item_id)
      ->execute()
      ->fetchCol();

    $this->assertGreaterThan(count($chunk_ids_run_1), count($chunk_ids_run_2),
      sprintf(
        'After run 2, distinct chunk ID count (%d) must exceed run-1 count (%d) — skip-delete must preserve in-progress chunks.',
        count($chunk_ids_run_2),
        count($chunk_ids_run_1),
      ));
  }

  /**
   * Tests that batch embedding indexes every chunk of a multi-chunk item.
   *
   * The embeddings provider used in these tests supports batch embeddings, so
   * the strategy sends chunks to the provider in batches. This verifies the
   * full indexing pipeline still stores exactly one vector record per chunk —
   * no chunk is dropped, duplicated, or misaligned by the batching path.
   */
  public function testBatchEmbeddingIndexesEveryChunk(): void {
    $this->drupalLogin($this->adminUser);
    $this->addBodyFieldToArticle();

    $long_text = str_repeat('Batch embedding coverage sentence to force multiple chunks per item. ', 500);
    $long_node = $this->drupalCreateNode([
      'type' => 'article',
      'title' => 'Batch Embedding Coverage Node',
      'body' => ['value' => $long_text, 'format' => 'plain_text'],
      'status' => 0,
    ]);
    $item_id = 'entity:node/' . $long_node->id() . ':en';

    $cron = \Drupal::service('cron');
    $db = \Drupal::database();
    $map_table = 'test_mysql_database_test_mysql_collection_map';

    // First cron run starts processing the large item.
    $cron->run();
    $status = $db->select('search_api_item', 'sai')
      ->fields('sai', ['processed_chunks', 'total_chunks'])
      ->condition('index_id', 'test_mysql_vdb_index')
      ->condition('item_id', $item_id)
      ->execute()
      ->fetchAssoc();
    $this->assertNotNull($status, 'The long node has a tracking row.');
    $total_chunks = (int) $status['total_chunks'];
    $this->assertGreaterThan(1, $total_chunks, 'The item produced multiple chunks.');

    // Run cron until the item is fully indexed (with a safety ceiling).
    $max_runs = (int) ceil($total_chunks / 10) + 2;
    $runs = 1;
    $processed = (int) $status['processed_chunks'];
    while ($processed < $total_chunks && $runs < $max_runs) {
      $cron->run();
      $runs++;
      $processed = (int) $db->select('search_api_item', 'sai')
        ->fields('sai', ['processed_chunks'])
        ->condition('index_id', 'test_mysql_vdb_index')
        ->condition('item_id', $item_id)
        ->execute()
        ->fetchField();
    }
    $this->assertEquals($total_chunks, $processed, 'All chunks were processed.');

    // Every chunk produced exactly one distinct stored vector record.
    $stored_chunk_ids = $db->select($map_table, 'm')
      ->fields('m', ['drupal_long_id'])
      ->distinct()
      ->condition('drupal_entity_id', $item_id)
      ->execute()
      ->fetchCol();
    $this->assertCount(
      $total_chunks,
      $stored_chunk_ids,
      sprintf('Expected %d distinct stored chunk IDs (one per chunk), got %d.', $total_chunks, count($stored_chunk_ids)),
    );
  }

  /**
   * Ensures the article content type has a body field shown in default display.
   *
   * Called by chunked-indexing tests to guarantee long body text is rendered
   * by the rendered_item processor.
   */
  protected function addBodyFieldToArticle(): void {
    if (!FieldStorageConfig::loadByName('node', 'body')) {
      FieldStorageConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'type' => 'text_long',
      ])->save();
    }
    if (!FieldConfig::loadByName('node', 'article', 'body')) {
      FieldConfig::create([
        'field_name' => 'body',
        'entity_type' => 'node',
        'bundle' => 'article',
        'label' => 'Body',
      ])->save();
    }
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('node.article.default');
    if ($display && !$display->getComponent('body')) {
      $display->setComponent('body', ['type' => 'text_default', 'weight' => 1])->save();
    }
  }

  /**
   * Tests that raw embedding vector is included in results when enabled.
   */
  public function testRawEmbeddingVectorInResults() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/config/search/search-api/server/test_mysql_vdb/edit');
    $this->submitForm([
      'backend_config[include_raw_embedding_vector]' => TRUE,
    ], 'Save');

    $this->drupalGet('admin/structure/views');
    if (!$this->getSession()->getPage()->hasLink('Test raw vector view')) {
      $this->drupalGet('admin/structure/views/add');
      $this->submitForm([
        'label' => 'Test raw vector view',
        'id' => 'test_raw_vector_view',
        'show[wizard_key]' => 'standard:search_api_index_test_mysql_vdb_index',
        'page[title]' => 'Test Raw Vector View',
        'page[create]' => 1,
        'page[path]' => 'test-raw-vector-view',
      ], 'Save and edit');

      // Add a search exposed filter.
      $this->drupalGet('admin/structure/views/nojs/add-handler/test_raw_vector_view/default/filter');
      $this->submitForm([
        'name[search_api_index_test_mysql_vdb_index.search_api_fulltext]' => 'search_api_index_test_mysql_vdb_index.search_api_fulltext',
      ], 'Add and configure filter criteria');

      // Expose the filter then save it.
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
      ];
      $this->submitForm($edit, 'Expose filter');
      $edit = [
        'options[expose_button][checkbox][checkbox]' => 1,
        'options[group_button][radios][radios]' => 0,
      ];
      $this->submitForm($edit, 'Apply');
      $this->submitForm([], 'Save');
    }

    // Go to the search view page.
    $this->drupalGet('test-raw-vector-view');
    $this->submitForm([
      'search_api_fulltext' => 'chocolate',
    ], 'Apply');

    // Programmatically fetch Search API results for deeper inspection.
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index = $index_storage->load('test_mysql_vdb_index');
    $query = $index->query();
    $query->keys('chocolate');
    $results = $query->execute();

    foreach ($results->getResultItems() as $item) {
      $extra_data = $item->getExtraData();
      $this->assertArrayHasKey('raw_vector', $extra_data, 'raw_vector is present in extra data');
      $this->assertIsArray($extra_data['raw_vector'], 'raw_vector is an array');
    }
  }

  /**
   * Tests that RAG queries enforce access control by default.
   *
   * Verifies the core security contract: search_api_bypass_access=FALSE (the
   * default set by RagAction::getRagResults()) excludes content the requesting
   * account cannot view, while an explicit TRUE allows it through.
   */
  public function testRagQueryRespectsAccessControlByDefault(): void {
    $index = \Drupal::entityTypeManager()
      ->getStorage('search_api_index')
      ->load('test_mysql_vdb_index');

    // The unpublished strawberry cake node is in the index (indexed as admin
    // during setUp) but must not be visible to anonymous users.
    $strawberry_entity_id = 'entity:node/' . $this->nodes[1]->id() . ':en';

    $switcher = \Drupal::service('account_switcher');
    $switcher->switchTo(new AnonymousUserSession());

    try {
      // search_api_bypass_access=FALSE: access enforced, unpublished excluded.
      $query = $index->query(['limit' => 10]);
      $query->keys('Strawberry Cheese');
      $query->setOption('search_api_bypass_access', FALSE);
      $results = $query->execute();

      $found_ids = array_map(
        fn($item) => $item->getExtraData('drupal_entity_id'),
        $results->getResultItems(),
      );
      $this->assertNotContains(
        $strawberry_entity_id,
        $found_ids,
        'Unpublished node must not appear in results when access enforcement is on.',
      );

      // search_api_bypass_access=TRUE: bypass enabled, unpublished included.
      $query = $index->query(['limit' => 10]);
      $query->keys('Strawberry Cheese');
      $query->setOption('search_api_bypass_access', TRUE);
      $results = $query->execute();

      $found_ids = array_map(
        fn($item) => $item->getExtraData('drupal_entity_id'),
        $results->getResultItems(),
      );
      $this->assertContains(
        $strawberry_entity_id,
        $found_ids,
        'Unpublished node must appear in results when access bypass is explicitly enabled.',
      );
    }
    finally {
      $switcher->switchBack();
    }
  }

}
