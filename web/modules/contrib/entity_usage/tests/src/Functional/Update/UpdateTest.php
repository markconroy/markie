<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_usage\Functional\Update;

use Drupal\Core\Database\Connection;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Update path tests.
 *
 * @group entity_usage
 */
class UpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The database connection.
   */
  protected Connection $connection;

  /**
   * The name of the test database.
   */
  protected string $databaseName;

  /**
   * The prefixed 'entity_usage' table.
   */
  protected string $tableName;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->connection = \Drupal::service('database');
    if ($this->connection->databaseType() == 'pgsql') {
      $this->databaseName = 'public';
    }
    else {
      $this->databaseName = $this->connection->getConnectionOptions()['database'];
    }
    $this->tableName = ($this->connection->getConnectionOptions()['prefix'] ?? '') . 'entity_usage';
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    if (file_exists(DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz')) {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
      ];
    }
    else {
      $this->databaseDumpFiles = [
        DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
      ];
    }
    $this->databaseDumpFiles[] = __DIR__ . '/../../../fixtures/update/post_update_test.php';
  }

  /**
   * @covers \entity_usage_post_update_clean_up_regenerate_queue
   * @covers \entity_usage_post_update_remove_unsupported_source_entity_types
   */
  public function testPostUpdates(): void {
    $this->assertSame(1, \Drupal::queue('entity_usage_regenerate_queue')->numberOfItems());
    $this->assertSame(['filter_format', 'node'], \Drupal::config('entity_usage.settings')->get('track_enabled_source_entity_types'));

    $this->runUpdates();
    $this->assertSame(0, \Drupal::queue('entity_usage_regenerate_queue')->numberOfItems());
    $this->assertSame(['node'], \Drupal::config('entity_usage.settings')->get('track_enabled_source_entity_types'));
  }

}
