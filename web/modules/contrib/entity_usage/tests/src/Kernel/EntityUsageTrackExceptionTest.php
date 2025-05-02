<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_usage_test\TestLogger;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests files and images tracking.
 *
 * @group entity_usage
 */
class EntityUsageTrackExceptionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'entity_usage',
    'entity_usage_test',
    'field',
    'filter',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    TestLogger::register($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'text',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'text',
      'label' => 'Text',
    ])->save();

    $this->installEntitySchema('entity_test');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['filter']);

    $this->config('entity_usage.settings')
      ->set('track_enabled_source_entity_types', ['entity_test'])
      ->set('track_enabled_target_entity_types', ['entity_test'])
      ->set('track_enabled_plugins', ['entity_usage_test'])
      ->save();
  }

  /**
   * Tests exceptions are logged when calculating entity usage.
   */
  public function testException(): void {
    $logger = $this->container->get(TestLogger::class);
    /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
    $key_value = $this->container->get('keyvalue')->get('entity_usage_test');
    $key_value->set('returns', [['entity_test|100']]);
    $entity1 = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
      'text' => $this->randomString(),
    ]);
    $logger->clear();
    $entity1->save();
    $this->assertEmpty(array_filter($logger->getLogs()));

    $this->assertSame([
      "method" => "entity_usage_test",
      "field_name" => "text",
      "count" => "1",
    ], $this->container->get('entity_usage.usage')->listTargets($entity1)['entity_test'][100][0]);

    $key_value->set('returns', [new \Exception('This is a test')]);
    $entity1->save();
    $logs = $logger->getLogs('error');
    $this->assertCount(1, $logs);
    $this->assertStringStartsWith('Calculating entity usage for field <em class="placeholder">text</em> on entity_test:1 using the <em class="placeholder">entity_usage_test</em> plugin threw <em class="placeholder">Exception</em>: This is a test', $logs[0]);
    // The entity usage information is removed since the call to get the old
    // info was successful but usage could not be determined for the new values.
    $this->assertEmpty($this->container->get('entity_usage.usage')->listTargets($entity1));

    $key_value->set('returns', [new \Exception('This is another test')]);
    $logger->clear();
    $entity1->save();
    $logs = $logger->getLogs('error');
    $this->assertCount(1, $logs);
    $this->assertStringStartsWith('Calculating entity usage for field <em class="placeholder">text</em> on entity_test:1 using the <em class="placeholder">entity_usage_test</em> plugin threw <em class="placeholder">Exception</em>: This is another test', $logs[0]);

    // Ensure exceptions are also caught during creation.
    $key_value->set('returns', [new \Exception('This is yet another test')]);
    $entity2 = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
      'text' => $this->randomString(),
    ]);
    $logger->clear();
    $entity2->save();
    $logs = $logger->getLogs('error');
    $this->assertCount(1, $logs);
    $this->assertStringStartsWith('Calculating entity usage for field <em class="placeholder">text</em> on entity_test:2 using the <em class="placeholder">entity_usage_test</em> plugin threw <em class="placeholder">Exception</em>: This is yet another test', $logs[0]);
  }

}
