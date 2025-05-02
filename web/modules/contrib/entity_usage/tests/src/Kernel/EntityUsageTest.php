<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\entity_usage\Events\EntityUsageEvent;
use Drupal\entity_usage\Events\Events;

/**
 * Tests the basic API operations of our tracking service.
 *
 * @group entity_usage
 *
 * @package Drupal\Tests\entity_usage\Kernel
 */
class EntityUsageTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'entity_usage'];

  /**
   * The entity type used in this test.
   *
   * @var string
   */
  protected $entityType = 'entity_test';

  /**
   * The bundle used in this test.
   *
   * @var string
   */
  protected $bundle = 'entity_test';

  /**
   * Some test entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $testEntities;

  /**
   * The injected database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $injectedDatabase;

  /**
   * The name of the table that stores entity usage information.
   *
   * @var string
   */
  protected $tableName;

  /**
   * State service for recording information received by event listeners.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->injectedDatabase = $this->container->get('database');

    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->tableName = 'entity_usage';

    // Create two test entities.
    $this->testEntities = $this->getTestEntities();

    $this->state = $this->container->get('state');
    $event_dispatcher = $this->container->get('event_dispatcher');
    $event_dispatcher->addListener(Events::USAGE_REGISTER,
      [$this, 'usageRegisterEventRecorder']);
    $event_dispatcher->addListener(Events::DELETE_BY_FIELD,
      [$this, 'usageDeleteByFieldEventRecorder']);
    $event_dispatcher->addListener(Events::DELETE_BY_SOURCE_ENTITY,
      [$this, 'usageDeleteBySourceEntityEventRecorder']);
    $event_dispatcher->addListener(Events::DELETE_BY_TARGET_ENTITY,
      [$this, 'usageDeleteByTargetEntityEventRecorder']);
    $event_dispatcher->addListener(Events::BULK_DELETE_DESTINATIONS,
      [$this, 'usageBulkTargetDeleteEventRecorder']);
    $event_dispatcher->addListener(Events::BULK_DELETE_SOURCES,
      [$this, 'usageBulkSourceDeleteEventRecorder']);
  }

  /**
   * Tests the listSources() and listTargets() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::listSources
   * @covers \Drupal\entity_usage\EntityUsage::listTargets
   */
  public function testListSources(): void {
    // Add additional entity to test with more than 1 source.
    $entity_3 = EntityTest::create(['name' => $this->randomMachineName()]);
    $entity_3->save();
    $this->testEntities[] = $entity_3;

    /** @var \Drupal\Core\Entity\EntityInterface $target_entity */
    $target_entity = $this->testEntities[0];
    /** @var \Drupal\Core\Entity\EntityInterface $source_entity */
    $source_entity = $this->testEntities[1];
    $source_vid = ($source_entity instanceof RevisionableInterface && $source_entity->getRevisionId()) ? $source_entity->getRevisionId() : 0;
    $field_name = 'body';
    $this->insertEntityUsage($source_entity, $target_entity, $field_name);

    // Add second source.
    $this->insertEntityUsage($this->testEntities[2], $target_entity, $field_name);

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $real_source_list = $entity_usage->listSources($target_entity);
    $expected_source_list = [
      $source_entity->getEntityTypeId() => [
        (string) $source_entity->id() => [
          0 => [
            'source_langcode' => $source_entity->language()->getId(),
            'source_vid' => $source_vid,
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
        ],
        (string) $entity_3->id() => [
          0 => [
            'source_langcode' => $entity_3->language()->getId(),
            'source_vid' => $entity_3->getRevisionId() ?: 0,
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_source_list, $real_source_list);

    // Test the limit parameter.
    unset($expected_source_list[$source_entity->getEntityTypeId()][(string) $source_entity->id()]);
    $real_source_list = $entity_usage->listSources($target_entity, TRUE, 1);
    $this->assertEquals($expected_source_list, $real_source_list);

    $real_target_list = $entity_usage->listTargets($source_entity);
    $expected_target_list = [
      $target_entity->getEntityTypeId() => [
        (string) $target_entity->id() => [
          0 => [
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_target_list, $real_target_list);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the listTargets() method using the revision option.
   *
   * @covers \Drupal\entity_usage\EntityUsage::listTargets
   */
  public function testListTargetRevisions(): void {
    /** @var \Drupal\Core\Entity\EntityInterface $target_entity */
    $target_entity = $this->testEntities[0];

    $storage = $this->entityTypeManager->getStorage('entity_test_mulrevpub');
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $field_name = 'body';

    // Original entity with no usage.
    $source_entity = EntityTestMulRevPub::create(['name' => $this->randomMachineName()]);
    $source_entity->save();
    $original_revision_id = $source_entity->getRevisionId();

    // Revisioned entity with 1 usage.
    $source_entity->set('name', $this->randomMachineName());
    $source_entity->setNewRevision(TRUE);
    $source_entity->save();
    $revision1_revision_id = $source_entity->getRevisionId();
    $this->insertEntityUsage($source_entity, $target_entity, $field_name);

    // Revisioned again with 1 usage.
    $source_entity->set('name', $this->randomMachineName());
    $source_entity->setNewRevision(TRUE);
    $source_entity->save();
    $this->insertEntityUsage($source_entity, $target_entity, $field_name);

    // Get targets across all revisions.
    $real_target_list = $entity_usage->listTargets($source_entity);

    $expected_target_list = [
      $target_entity->getEntityTypeId() => [
        (string) $target_entity->id() => [
          0 => [
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
          1 => [
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_target_list, $real_target_list);

    // Get targets from original entity.
    $real_target_list = $entity_usage->listTargets($source_entity, $original_revision_id);
    $this->assertEquals([], $real_target_list);

    // Get targets from revisioned entity.
    $real_target_list = $entity_usage->listTargets($source_entity, $revision1_revision_id);

    $expected_target_list = [
      $target_entity->getEntityTypeId() => [
        (string) $target_entity->id() => [
          0 => [
            'method' => 'entity_reference',
            'field_name' => $field_name,
            'count' => 1,
          ],
        ],
      ],
    ];

    $this->assertEquals($expected_target_list, $real_target_list);

    // Invalid revision ID.
    $real_target_list = $entity_usage->listTargets($source_entity, 9999);
    $this->assertEquals([], $real_target_list);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Inserts a row into the usage table.
   *
   * @param \Drupal\Core\Entity\EntityInterface $source
   *   The source entity.
   * @param \Drupal\Core\Entity\EntityInterface $target
   *   The target entity.
   * @param string $field_name
   *   The field name.
   */
  protected function insertEntityUsage(EntityInterface $source, EntityInterface $target, string $field_name): void {
    $source_vid = ($source instanceof RevisionableInterface && $source->getRevisionId()) ? $source->getRevisionId() : 0;

    $this->injectedDatabase->insert($this->tableName)
      ->fields([
        'target_id' => $target->id(),
        'target_type' => $target->getEntityTypeId(),
        'source_id' => $source->id(),
        'source_type' => $source->getEntityTypeId(),
        'source_langcode' => $source->language()->getId(),
        'source_vid' => $source_vid,
        'method' => 'entity_reference',
        'field_name' => $field_name,
        'count' => 1,
      ])
      ->execute();
  }

  /**
   * Tests the registerUsage() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::registerUsage
   */
  public function testRegisterUsage(): void {
    $entity = $this->testEntities[0];
    $field_name = 'body';
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');

    // Register a new usage.
    $entity_usage->registerUsage($entity->id(), $entity->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', $field_name, 1);

    $event = \Drupal::state()->get('entity_usage_events_test.usage_register', []);

    $this->assertSame($event[0]['event_name'], Events::USAGE_REGISTER);
    $this->assertSame($event[0]['target_id'], $entity->id());
    $this->assertSame($event[0]['target_type'], $entity->getEntityTypeId());
    $this->assertSame($event[0]['source_id'], 1);
    $this->assertSame($event[0]['source_type'], 'foo');
    $this->assertSame($event[0]['source_langcode'], 'en');
    $this->assertSame($event[0]['source_vid'], 1);
    $this->assertSame($event[0]['method'], 'entity_reference');
    $this->assertSame($event[0]['field_name'], $field_name);
    $this->assertSame($event[0]['count'], 1);
    $this->assertCount(1, $event);

    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.target_id', $entity->id())
      ->condition('e.target_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchField();

    $this->assertEquals(1, $real_usage);

    // Delete the record.
    $entity_usage->registerUsage($entity->id(), $entity->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', $field_name, 0);

    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.target_id', $entity->id())
      ->condition('e.target_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchField();

    $this->assertFalse($real_usage);

    // Test that config settings are respected.
    $this->container->get('config.factory')
      ->getEditable('entity_usage.settings')
      // No entities tracked at all.
      ->set('track_enabled_target_entity_types', [])
      ->save();
    drupal_flush_all_caches();
    $this->container->get('entity_usage.usage')->registerUsage($entity->id(), $entity->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', $field_name, 1);

    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.target_id', $entity->id())
      ->condition('e.target_type', $entity->getEntityTypeId())
      ->execute()
      ->fetchField();

    $this->assertFalse($real_usage);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests that our hook correctly blocks a usage from being tracked.
   */
  public function testEntityUsageBlockTrackingHook(): void {
    $this->container->get('module_installer')->install([
      'image',
      'media',
      'path',
      'views',
      'entity_usage_test',
    ]);

    $entity = $this->testEntities[0];
    $field_name = 'body';
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $entity_usage->registerUsage($entity->id(), $entity->getEntityTypeId(), 1, 'foo', 'en', 0, 'entity_reference', $field_name, 31);
    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.target_id', $entity->id())
      ->execute()
      ->fetchField();

    // In entity_usage_test_entity_usage_block_tracking() we block all
    // transactions that try to add "31" as count. We expect then the usage to
    // be 0.
    $this->assertEquals(0, $real_usage);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the bulkDeleteTargets() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::bulkDeleteTargets
   */
  public function testBulkDeleteTargets(): void {
    $entity_type = $this->testEntities[0]->getEntityTypeId();

    // Create 2 fake registers on the database table, one for each entity.
    foreach ($this->testEntities as $entity) {
      $this->injectedDatabase->insert($this->tableName)
        ->fields([
          'target_id' => $entity->id(),
          'target_type' => $entity_type,
          'source_id' => 1,
          'source_type' => 'foo',
          'source_langcode' => 'en',
          'source_vid' => 1,
          'method' => 'entity_reference',
          'field_name' => 'body',
          'count' => 1,
        ])
        ->execute();
    }

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $entity_usage->bulkDeleteTargets($entity_type);

    $event = \Drupal::state()->get('entity_usage_events_test.usage_bulk_delete_targets', []);

    $this->assertSame($event['event_name'], Events::BULK_DELETE_DESTINATIONS);
    $this->assertSame($event['target_id'], NULL);
    $this->assertSame($event['target_type'], $entity_type);
    $this->assertSame($event['source_id'], NULL);
    $this->assertSame($event['source_type'], NULL);
    $this->assertSame($event['source_langcode'], NULL);
    $this->assertSame($event['source_vid'], NULL);
    $this->assertSame($event['method'], NULL);
    $this->assertSame($event['field_name'], NULL);
    $this->assertSame($event['count'], NULL);

    // Check if there are no records left.
    $count = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.target_type', $entity_type)
      ->execute()
      ->fetchField();
    $this->assertFalse($count);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the bulkDeleteSources() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::bulkDeleteSources
   */
  public function testBulkDeleteSources(): void {
    $entity_type = $this->testEntities[0]->getEntityTypeId();

    // Create 2 fake registers on the database table, one for each entity.
    foreach ($this->testEntities as $entity) {
      $source_vid = ($entity instanceof RevisionableInterface && $entity->getRevisionId()) ? $entity->getRevisionId() : 0;
      $this->injectedDatabase->insert($this->tableName)
        ->fields([
          'target_id' => 1,
          'target_type' => 'foo',
          'source_id' => $entity->id(),
          'source_type' => $entity_type,
          'source_langcode' => $entity->language()->getId(),
          'source_vid' => $source_vid,
          'method' => 'entity_reference',
          'field_name' => 'body',
          'count' => 1,
        ])
        ->execute();
    }

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $entity_usage->bulkDeleteSources($entity_type);

    $event = \Drupal::state()->get('entity_usage_events_test.usage_bulk_delete_sources', []);

    $this->assertSame($event['event_name'], Events::BULK_DELETE_SOURCES);
    $this->assertSame($event['target_id'], NULL);
    $this->assertSame($event['target_type'], NULL);
    $this->assertSame($event['source_id'], NULL);
    $this->assertSame($event['source_type'], $entity_type);
    $this->assertSame($event['source_langcode'], NULL);
    $this->assertSame($event['source_vid'], NULL);
    $this->assertSame($event['method'], NULL);
    $this->assertSame($event['field_name'], NULL);
    $this->assertSame($event['count'], NULL);

    // Check if there are no records left.
    $count = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e', ['count'])
      ->condition('e.source_type', $entity_type)
      ->execute()
      ->fetchField();
    $this->assertFalse($count);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the deleteByField() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::deleteByField
   */
  public function testDeleteByField(): void {
    $entity_type = $this->testEntities[0]->getEntityTypeId();

    // Create 2 fake registers on the database table, one for each entity.
    $i = 0;
    foreach ($this->testEntities as $entity) {
      $source_vid = ($entity instanceof RevisionableInterface && $entity->getRevisionId()) ? $entity->getRevisionId() : 0;
      $this->injectedDatabase->insert($this->tableName)
        ->fields([
          'target_id' => 1,
          'target_type' => 'foo',
          'source_id' => $entity->id(),
          'source_type' => $entity_type,
          'source_langcode' => $entity->language()->getId(),
          'source_vid' => $source_vid,
          'method' => 'entity_reference',
          'field_name' => 'body' . $i++,
          'count' => 1,
        ])
        ->execute();
    }

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    // Delete only one of them, by field.
    $entity_usage->deleteByField($entity_type, 'body1');

    $event = \Drupal::state()->get('entity_usage_events_test.usage_delete_by_field', []);

    $this->assertSame($event['event_name'], Events::DELETE_BY_FIELD);
    $this->assertSame($event['target_id'], NULL);
    $this->assertSame($event['target_type'], NULL);
    $this->assertSame($event['source_id'], NULL);
    $this->assertSame($event['source_type'], $entity_type);
    $this->assertSame($event['source_langcode'], NULL);
    $this->assertSame($event['source_vid'], NULL);
    $this->assertSame($event['method'], NULL);
    $this->assertSame($event['field_name'], 'body1');
    $this->assertSame($event['count'], NULL);

    $result = $this->injectedDatabase->select($this->tableName, 'e')
      ->fields('e')
      ->condition('e.source_type', $entity_type)
      ->execute()
      ->fetchAll();
    $source_vid = ($this->testEntities[0] instanceof RevisionableInterface && $this->testEntities[0]->getRevisionId()) ? $this->testEntities[0]->getRevisionId() : 0;
    $expected_result = [
      'target_id' => '1',
      'target_id_string' => NULL,
      'target_type' => 'foo',
      'source_id' => (string) $this->testEntities[0]->id(),
      'source_id_string' => NULL,
      'source_type' => $entity_type,
      'source_langcode' => $this->testEntities[0]->language()->getId(),
      'source_vid' => $source_vid,
      'method' => 'entity_reference',
      'field_name' => 'body0',
      'count' => 1,
    ];
    $this->assertEquals([(object) $expected_result], $result);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the deleteBySourceEntity() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::deleteBySourceEntity
   */
  public function testDeleteBySourceEntity(): void {
    // Create 2 fake registers on the database table, one for each entity.
    $i = 0;
    foreach ($this->testEntities as $entity) {
      $i++;
      $source_vid = ($entity instanceof RevisionableInterface && $entity->getRevisionId()) ? $entity->getRevisionId() : 0;
      $this->injectedDatabase->insert($this->tableName)
        ->fields([
          'target_id' => $i,
          'target_type' => 'fake_type_' . $i,
          'source_id' => $entity->id(),
          'source_type' => $entity->getEntityTypeId(),
          'source_langcode' => $entity->language()->getId(),
          'source_vid' => $source_vid,
          'method' => 'entity_reference',
          'field_name' => 'body',
          'count' => 1,
        ])
        ->execute();
    }

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    // Delete only one of them, by source.
    $entity_usage->deleteBySourceEntity($this->testEntities[0]->id(), $this->testEntities[0]->getEntityTypeId());

    $event = \Drupal::state()->get('entity_usage_events_test.usage_delete_by_source_entity', []);

    $this->assertSame($event['event_name'], Events::DELETE_BY_SOURCE_ENTITY);
    $this->assertSame($event['target_id'], NULL);
    $this->assertSame($event['target_type'], NULL);
    $this->assertSame($event['source_id'], $this->testEntities[0]->id());
    $this->assertSame($event['source_type'], $this->testEntities[0]->getEntityTypeId());
    $this->assertSame($event['source_langcode'], NULL);
    $this->assertSame($event['source_vid'], NULL);
    $this->assertSame($event['method'], NULL);
    $this->assertSame($event['field_name'], NULL);
    $this->assertSame($event['count'], NULL);

    // The non-affected record is still there.
    $real_target_list = $entity_usage->listTargets($this->testEntities[1]);
    $expected_target_list = [
      'fake_type_2' => [
        '2' => [
          0 => [
            'method' => 'entity_reference',
            'field_name' => 'body',
            'count' => '1',
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_target_list, $real_target_list);

    // The affected record is gone.
    $real_target_list = $entity_usage->listSources($this->testEntities[0]);
    $this->assertEquals([], $real_target_list);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the deleteByTargetEntity() method.
   *
   * @covers \Drupal\entity_usage\EntityUsage::deleteByTargetEntity
   */
  public function testDeleteByTargetEntity(): void {
    // Create 2 fake registers on the database table, one for each entity.
    $i = 0;
    foreach ($this->testEntities as $entity) {
      $i++;
      $this->injectedDatabase->insert($this->tableName)
        ->fields([
          'target_id' => $entity->id(),
          'target_type' => $entity->getEntityTypeId(),
          'source_id' => $i,
          'source_type' => 'fake_type_' . $i,
          'source_langcode' => 'en',
          'source_vid' => $i,
          'method' => 'entity_reference',
          'field_name' => 'body' . $i,
          'count' => 1,
        ])
        ->execute();
    }

    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    // Delete only one of them, by target.
    $entity_usage->deleteByTargetEntity($this->testEntities[0]->id(), $this->testEntities[0]->getEntityTypeId());

    $event = \Drupal::state()->get('entity_usage_events_test.usage_delete_by_target_entity', []);

    $this->assertSame($event['event_name'], Events::DELETE_BY_TARGET_ENTITY);
    $this->assertSame($event['target_id'], $this->testEntities[0]->id());
    $this->assertSame($event['target_type'], $this->testEntities[0]->getEntityTypeId());
    $this->assertSame($event['source_id'], NULL);
    $this->assertSame($event['source_type'], NULL);
    $this->assertSame($event['source_langcode'], NULL);
    $this->assertSame($event['method'], NULL);
    $this->assertSame($event['field_name'], NULL);
    $this->assertSame($event['count'], NULL);

    // The non-affected record is still there.
    $real_source_list = $entity_usage->listSources($this->testEntities[1]);
    $expected_source_list = [
      'fake_type_2' => [
        '2' => [
          0 => [
            'source_langcode' => 'en',
            'source_vid' => '2',
            'method' => 'entity_reference',
            'field_name' => 'body2',
            'count' => 1,
          ],
        ],
      ],
    ];
    $this->assertEquals($expected_source_list, $real_source_list);

    // The affected record is gone.
    $real_source_list = $entity_usage->listSources($this->testEntities[0]);
    $this->assertEquals([], $real_source_list);

    // Clean back the environment.
    $this->injectedDatabase->truncate($this->tableName);
  }

  /**
   * Tests the registerUsage() and bulkInsert() methods.
   *
   * @covers \Drupal\entity_usage\EntityUsage::registerUsage
   * @covers \Drupal\entity_usage\EntityUsage::enableBulkInsert
   * @covers \Drupal\entity_usage\EntityUsage::bulkInsert
   */
  public function testBulkInserting(): void {
    $field_name = 'body';
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');

    $entity_usage->enableBulkInsert();

    // Register a new usage.
    $entity_usage->registerUsage($this->testEntities[0]->id(), $this->testEntities[0]->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', $field_name, 1);
    $event = \Drupal::state()->get('entity_usage_events_test.usage_register', []);
    $this->assertEmpty($event);
    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $real_usage);

    // Register another new usage.
    $entity_usage->registerUsage($this->testEntities[1]->id(), $this->testEntities[1]->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', $field_name, 2);
    $this->assertEmpty($event);
    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')->countQuery()->execute()->fetchField();
    $this->assertEquals(0, $real_usage);

    // Register with string IDs.
    $entity_usage->registerUsage('a string', 'fake', 'another string', 'foo', 'en', 1, 'entity_reference', $field_name, 3);

    // Do the bulk insert.
    $entity_usage->bulkInsert();

    $event = \Drupal::state()->get('entity_usage_events_test.usage_register', []);
    $this->assertSame($event[0]['event_name'], Events::USAGE_REGISTER);
    $this->assertSame($event[0]['target_id'], $this->testEntities[0]->id());
    $this->assertSame($event[0]['target_type'], $this->testEntities[0]->getEntityTypeId());
    $this->assertSame($event[0]['source_id'], 1);
    $this->assertSame($event[0]['source_type'], 'foo');
    $this->assertSame($event[0]['source_langcode'], 'en');
    $this->assertSame($event[0]['source_vid'], 1);
    $this->assertSame($event[0]['method'], 'entity_reference');
    $this->assertSame($event[0]['field_name'], $field_name);
    $this->assertSame($event[0]['count'], 1);
    $this->assertSame($event[1]['event_name'], Events::USAGE_REGISTER);
    $this->assertSame($event[1]['target_id'], $this->testEntities[1]->id());
    $this->assertSame($event[1]['target_type'], $this->testEntities[1]->getEntityTypeId());
    $this->assertSame($event[1]['source_id'], 1);
    $this->assertSame($event[1]['source_type'], 'foo');
    $this->assertSame($event[1]['source_langcode'], 'en');
    $this->assertSame($event[1]['source_vid'], 1);
    $this->assertSame($event[1]['method'], 'entity_reference');
    $this->assertSame($event[1]['field_name'], $field_name);
    $this->assertSame($event[1]['count'], 2);
    $this->assertSame($event[2]['event_name'], Events::USAGE_REGISTER);
    $this->assertSame($event[2]['target_id'], 'a string');
    $this->assertSame($event[2]['target_type'], 'fake');
    $this->assertSame($event[2]['source_id'], 'another string');
    $this->assertSame($event[2]['source_type'], 'foo');
    $this->assertSame($event[2]['source_langcode'], 'en');
    $this->assertSame($event[2]['source_vid'], 1);
    $this->assertSame($event[2]['method'], 'entity_reference');
    $this->assertSame($event[2]['field_name'], $field_name);
    $this->assertSame($event[2]['count'], 3);
    $this->assertCount(3, $event);

    $real_usage = $this->injectedDatabase->select($this->tableName, 'e')->countQuery()->execute()->fetchField();
    $this->assertEquals(3, $real_usage);
  }

  /**
   * Tests the truncateTable() methods.
   *
   * @covers \Drupal\entity_usage\EntityUsage::truncateTable
   */
  public function testTruncateTable(): void {
    $this->assertSame(0, (int) $this->container->get('database')->select('entity_usage')->countQuery()->execute()->fetchField());
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $entity_usage->registerUsage($this->testEntities[0]->id(), $this->testEntities[0]->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', 'body', 1);
    $entity_usage->registerUsage($this->testEntities[1]->id(), $this->testEntities[1]->getEntityTypeId(), 1, 'foo', 'en', 1, 'entity_reference', 'body', 2);
    $this->assertSame(2, (int) $this->container->get('database')->select('entity_usage')->countQuery()->execute()->fetchField());

    $entity_usage->truncateTable();
    $this->assertSame(0, (int) $this->container->get('database')->select('entity_usage')->countQuery()->execute()->fetchField());
  }

  /**
   * Creates two test entities.
   *
   * @return \Drupal\entity_test\Entity\EntityTest[]
   *   An array of entity objects.
   */
  protected function getTestEntities(): array {
    $content_entity_1 = EntityTest::create(['name' => $this->randomMachineName()]);
    $content_entity_1->save();
    $content_entity_2 = EntityTest::create(['name' => $this->randomMachineName()]);
    $content_entity_2->save();

    return [
      $content_entity_1,
      $content_entity_2,
    ];
  }

  /**
   * Reacts to a register event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageRegisterEventRecorder(EntityUsageEvent $event, $name): void {
    $events = $this->state->get('entity_usage_events_test.usage_register', []);
    $events[] = [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ];
    $this->state->set('entity_usage_events_test.usage_register', $events);
  }

  /**
   * Reacts to delete by field event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageDeleteByFieldEventRecorder(EntityUsageEvent $event, $name): void {
    $this->state->set('entity_usage_events_test.usage_delete_by_field', [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ]);
  }

  /**
   * Reacts to delete by source entity event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageDeleteBySourceEntityEventRecorder(EntityUsageEvent $event, $name): void {
    $this->state->set('entity_usage_events_test.usage_delete_by_source_entity', [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ]);
  }

  /**
   * Reacts to delete by target entity event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageDeleteByTargetEntityEventRecorder(EntityUsageEvent $event, $name): void {
    $this->state->set('entity_usage_events_test.usage_delete_by_target_entity', [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ]);
  }

  /**
   * Reacts to bulk target delete event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageBulkTargetDeleteEventRecorder(EntityUsageEvent $event, $name): void {
    $this->state->set('entity_usage_events_test.usage_bulk_delete_targets', [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ]);
  }

  /**
   * Reacts to bulk source delete event.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The entity usage event.
   * @param string $name
   *   The name of the event.
   */
  public function usageBulkSourceDeleteEventRecorder(EntityUsageEvent $event, $name): void {
    $this->state->set('entity_usage_events_test.usage_bulk_delete_sources', [
      'event_name' => $name,
      'target_id' => $event->getTargetEntityId(),
      'target_type' => $event->getTargetEntityType(),
      'source_id' => $event->getSourceEntityId(),
      'source_type' => $event->getSourceEntityType(),
      'source_langcode' => $event->getSourceEntityLangcode(),
      'source_vid' => $event->getSourceEntityRevisionId(),
      'method' => $event->getMethod(),
      'field_name' => $event->getFieldName(),
      'count' => $event->getCount(),
    ]);
  }

}
