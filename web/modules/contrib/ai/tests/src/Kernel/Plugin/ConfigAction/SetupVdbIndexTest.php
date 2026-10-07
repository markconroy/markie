<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\ConfigAction;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ai\Plugin\ConfigAction\SetupVdbIndex;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\AbstractLogger;

/**
 * Kernel tests for the SetupVdbIndex config action plugin.
 */
#[CoversClass(SetupVdbIndex::class)]
#[Group('ai')]
#[RunTestsInSeparateProcesses]
class SetupVdbIndexTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'search_api',
    'search_api_test',
    'system',
    'user',
  ];

  /**
   * The config action plugin under test.
   *
   * @var \Drupal\ai\Plugin\ConfigAction\SetupVdbIndex
   */
  private SetupVdbIndex $setupVdbIndex;

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

    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installConfig(['ai', 'search_api']);
    Server::create([
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend' => 'search_api_test',
    ])->save();
    $plugin_manager = $this->container->get('plugin.manager.config_action');
    $this->setupVdbIndex = $plugin_manager->createInstance('setupVdbIndex');

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
    $value = [
      'id' => 'test_index',
      'name' => 'Test Index',
      'field_settings' => [],
      'server' => 'test_server',
    ];

    $this->setupVdbIndex->apply('search_api.index.test_index', $value);
    $index = Index::load('test_index');
    $this->assertInstanceOf(Index::class, $index);
    $uuid = $index->uuid();
    $this->assertSame([], $this->getSkipRecords());

    $this->setupVdbIndex->apply('search_api.index.test_index', $value);
    $index = Index::load('test_index');
    $this->assertInstanceOf(Index::class, $index);
    $this->assertSame($uuid, $index->uuid());

    $records = $this->getSkipRecords();
    $this->assertCount(1, $records);
    $this->assertSame(RfcLogLevel::NOTICE, $records[0]['level']);
    $this->assertStringContainsString('The search index "@id" already exists', $records[0]['message']);
    $this->assertSame('test_index', $records[0]['context']['@id']);
  }

  /**
   * Tests that an existing index is left untouched even if the value differs.
   */
  public function testApplyExistingIndexIsNotModified(): void {
    $value = [
      'id' => 'test_index',
      'name' => 'Test Index',
      'field_settings' => [],
      'server' => 'test_server',
    ];
    $this->setupVdbIndex->apply('search_api.index.test_index', $value);

    $value['name'] = 'Renamed Index';
    $this->setupVdbIndex->apply('search_api.index.test_index', $value);

    $this->assertCount(1, $this->getSkipRecords());
    $this->assertSame('Test Index', Index::load('test_index')->label());
  }

  /**
   * Tests re-applying through the config action manager, as recipes do.
   */
  public function testApplyThroughConfigActionManager(): void {
    $manager = $this->container->get('plugin.manager.config_action');
    $value = [
      'id' => 'test_index',
      'name' => 'Test Index',
      'field_settings' => [],
      'server' => 'test_server',
    ];

    $manager->applyAction('setupVdbIndex', 'search_api.index.test_index', $value);
    $uuid = Index::load('test_index')->uuid();

    $manager->applyAction('setupVdbIndex', 'search_api.index.test_index', $value);
    $this->assertSame($uuid, Index::load('test_index')->uuid());
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

}
