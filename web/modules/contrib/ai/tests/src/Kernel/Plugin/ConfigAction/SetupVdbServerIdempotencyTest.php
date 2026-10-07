<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\ConfigAction;

use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ai\Plugin\ConfigAction\SetupVdbServer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Kernel tests for idempotent SetupVdbServer config actions.
 */
#[CoversClass(SetupVdbServer::class)]
#[Group('ai')]
#[RunTestsInSeparateProcesses]
class SetupVdbServerIdempotencyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_search',
    'ai_test',
    'key',
    'search_api',
    'search_api_test',
    'system',
    'user',
  ];

  /**
   * The config action plugin under test.
   */
  private SetupVdbServer $setupVdbServer;

  /**
   * A logger that records every message it receives.
   *
   * @var \Psr\Log\AbstractLogger
   */
  private AbstractLogger $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['ai', 'ai_search', 'ai_test', 'search_api']);
    $this->config('ai.settings')
      ->set('default_providers.embeddings', [
        'provider_id' => 'echoai',
        'model_id' => 'test_model',
      ])
      ->save();
    $plugin_manager = $this->container->get('plugin.manager.config_action');
    $this->setupVdbServer = $plugin_manager->createInstance('setupVdbServerWithDefaults');

    // Capture log messages so tests can assert on them.
    $this->logger = new class() extends AbstractLogger {

      /**
       * The captured log records.
       *
       * @var array<int, array{level: mixed, message: string, context: array}>
       */
      public array $records = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->records[] = [
          'level' => $level,
          'message' => (string) $message,
          'context' => $context,
        ];
      }

    };
    $this->container->get('logger.factory')->addLogger($this->logger);
  }

  /**
   * Tests that applying matching configuration twice is a no-op.
   */
  public function testApplyMatchingConfigurationTwice(): void {
    $value = $this->getServerValue();

    $this->setupVdbServer->apply('search_api.server.test_server', $value);
    $server = Server::load('test_server');
    $this->assertInstanceOf(Server::class, $server);
    $uuid = $server->uuid();
    $this->assertSame([], $this->getSkipRecords());

    $this->setupVdbServer->apply('search_api.server.test_server', $value);
    $server = Server::load('test_server');
    $this->assertInstanceOf(Server::class, $server);
    $this->assertSame($uuid, $server->uuid());

    $records = $this->getSkipRecords();
    $this->assertCount(1, $records);
    $this->assertSame(RfcLogLevel::NOTICE, $records[0]['level']);
    $this->assertStringContainsString('The VDB server "@id" already exists with matching remote configuration', $records[0]['message']);
    $this->assertSame('test_server', $records[0]['context']['@id']);
  }

  /**
   * Tests that values are compared using their config schema types.
   *
   * Config::save() casts values to the types declared in the schema, so an
   * unquoted number in a recipe is stored as a string. Re-applying the recipe
   * must not report that as a conflict.
   */
  public function testApplyMatchingConfigurationWithIntegerValues(): void {
    $value = $this->getServerValue();
    $value['backend_config']['embedding_strategy_configuration']['chunk_size'] = 300;
    $value['backend_config']['embedding_strategy_configuration']['chunk_min_overlap'] = 100;

    $this->setupVdbServer->apply('search_api.server.test_server', $value);
    $server = Server::load('test_server');
    $this->assertInstanceOf(Server::class, $server);
    $uuid = $server->uuid();
    // The schema declares these as strings, so they were cast on save.
    $stored = $server->getBackendConfig()['embedding_strategy_configuration'];
    $this->assertSame('300', $stored['chunk_size']);
    $this->assertSame('100', $stored['chunk_min_overlap']);

    // Re-applying with the same integers is a no-op.
    $this->setupVdbServer->apply('search_api.server.test_server', $value);
    $this->assertCount(1, $this->getSkipRecords());

    // Re-applying with the equivalent strings is a no-op as well.
    $this->setupVdbServer->apply('search_api.server.test_server', $this->getServerValue());
    $this->assertCount(2, $this->getSkipRecords());
    $this->assertSame($uuid, Server::load('test_server')->uuid());
  }

  /**
   * Tests that conflicting remote configuration is rejected.
   */
  public function testApplyConflictingConfiguration(): void {
    $value = $this->getServerValue();
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    $value['backend_config']['embedding_strategy_configuration']['chunk_size'] = '600';

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('backend_config.embedding_strategy_configuration.chunk_size');
    $this->setupVdbServer->apply('search_api.server.test_server', $value);
  }

  /**
   * Tests that a changed default embeddings model is a conflict.
   */
  public function testApplyConflictingEmbeddingsModel(): void {
    $value = $this->getServerValue();
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    $this->config('ai.settings')
      ->set('default_providers.embeddings.model_id', 'other_model')
      ->save();

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('at backend_config.embeddings_engine.');
    $this->setupVdbServer->apply('search_api.server.test_server', $value);
  }

  /**
   * Tests that a stored vector size that no longer matches is a conflict.
   */
  public function testApplyConflictingStoredDimensions(): void {
    $value = $this->getServerValue();
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    $server = Server::load('test_server');
    $backend_config = $server->getBackendConfig();
    $backend_config['embeddings_engine_configuration']['dimensions'] = 2;
    $server->setBackendConfig($backend_config)->save();

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('at backend_config.embeddings_engine_configuration.dimensions.');
    $this->setupVdbServer->apply('search_api.server.test_server', $value);
  }

  /**
   * Tests that a different backend is a conflict.
   */
  public function testApplyConflictingBackend(): void {
    $value = $this->getServerValue();
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    $value['backend'] = 'search_api_test';

    $this->expectException(ConfigActionException::class);
    $this->expectExceptionMessage('at backend.');
    $this->setupVdbServer->apply('search_api.server.test_server', $value);
  }

  /**
   * Tests that settings unrelated to the remote collection are not compared.
   */
  public function testApplyIgnoresNonRemoteChanges(): void {
    $value = $this->getServerValue();
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    $value['name'] = 'Renamed Server';
    $this->setupVdbServer->apply('search_api.server.test_server', $value);

    // The existing server is neither rejected nor updated.
    $this->assertCount(1, $this->getSkipRecords());
    $this->assertSame('Test Server', Server::load('test_server')->label());
  }

  /**
   * Tests re-applying through the config action manager, as recipes do.
   */
  public function testApplyThroughConfigActionManager(): void {
    $manager = $this->container->get('plugin.manager.config_action');
    $value = $this->getServerValue();

    $manager->applyAction('setupVdbServerWithDefaults', 'search_api.server.test_server', $value);
    $uuid = Server::load('test_server')->uuid();

    $manager->applyAction('setupVdbServerWithDefaults', 'search_api.server.test_server', $value);
    $this->assertSame($uuid, Server::load('test_server')->uuid());
    $this->assertCount(1, $this->getSkipRecords());
  }

  /**
   * Gets the captured "already exists" notices.
   *
   * @return array<int, array{level: mixed, message: string, context: array}>
   *   The matching log records.
   */
  private function getSkipRecords(): array {
    return array_values(array_filter(
      $this->logger->records,
      static fn (array $record): bool => str_contains($record['message'], 'already exists'),
    ));
  }

  /**
   * Gets a valid server config action value.
   *
   * @return array
   *   The server configuration.
   */
  private function getServerValue(): array {
    return [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend' => 'ai_test_vdb_config',
      'backend_config' => [
        'database' => 'echo_db',
        'database_settings' => [
          'database_name' => 'test_database',
          'collection' => 'test_collection',
          'metric' => 'cosine_similarity',
        ],
        'embedding_strategy' => 'contextual_chunks',
        'embedding_strategy_configuration' => [
          'chunk_size' => '300',
          'chunk_min_overlap' => '100',
          'contextual_content_max_percentage' => '30',
        ],
      ],
    ];
  }

}
