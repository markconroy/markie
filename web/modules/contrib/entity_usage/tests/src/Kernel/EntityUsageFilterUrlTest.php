<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\filter\Entity\FilterFormat;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Tests URLs in text tracking.
 *
 * @group entity_usage
 */
class EntityUsageFilterUrlTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'entity_usage',
    'field',
    'filter',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'text_no_url_filter',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'text_no_url_filter',
      'label' => 'Text No URL Filter',
    ])->save();

    FieldStorageConfig::create([
      'type' => 'text_long',
      'entity_type' => 'entity_test',
      'field_name' => 'text_url_filter',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'text_url_filter',
      'label' => 'Text URL Filter',
    ])->save();

    $format = FilterFormat::create([
      'format' => 'no_url_format',
      'name' => 'No URL format',
    ]);
    $format->setFilterConfig('filter_url', [
      'status' => 0,
    ]);
    $format->save();

    // Add another text format specifying the URL filter.
    $format = FilterFormat::create([
      'format' => 'url_format',
      'name' => 'URL format',
    ]);
    $format->setFilterConfig('filter_url', [
      'status' => 1,
      'settings' => [
        'filter_url_length' => 30,
      ],
    ]);
    $format->save();

    $this->installEntitySchema('entity_test');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installConfig(['filter']);

    $this->config('entity_usage.settings')
      ->set('track_enabled_source_entity_types', ['entity_test'])
      ->set('track_enabled_target_entity_types', ['entity_test'])
      ->set('track_enabled_plugins', ['html_link'])
      ->set('site_domains', ['http://localhost'])
      ->save();
    $this->container->get('kernel')->resetContainer();
  }

  /**
   * Tests text URLs are tracked if the filter is set up.
   */
  public function testUrlTracking(): void {
    $entity1 = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
    ]);
    $entity1->save();

    $entity2 = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
    ]);
    $entity2->save();

    $entity3 = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
      'text_no_url_filter' => ['value' => $entity1->toUrl()->setAbsolute()->toString(), 'format' => 'no_url_format'],
      'text_url_filter' => ['value' => $entity2->toUrl()->setAbsolute()->toString(), 'format' => 'url_format'],
    ]);
    $entity3->save();

    // We should have a single usage in the text_url_filter field.
    /** @var \Drupal\entity_usage\EntityUsage $entity_usage */
    $entity_usage = $this->container->get('entity_usage.usage');
    $targets = $entity_usage->listTargets($entity3);
    $this->assertCount(1, $targets['entity_test']);
    $this->assertSame(
      [
        [
          'method' => 'html_link',
          'field_name' => 'text_url_filter',
          'count' => '1',
        ],
      ],
      $targets['entity_test'][2]
    );
  }

}
