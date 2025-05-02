<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\Tests\file\Functional\FileFieldCreationTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;

/**
 * Tests files and images tracking.
 *
 * @group entity_usage
 */
class EntityUsageTrackFileTest extends KernelTestBase {

  use FileFieldCreationTrait;
  use TestFileCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'entity_usage',
    'field',
    'file',
    'filter',
    'image',
    'system',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('file');
    $this->installSchema('entity_usage', ['entity_usage']);
    $this->installSchema('file', ['file_usage']);

    // Add a file field.
    $this->createFileField(
      'file',
      'entity_test',
      'entity_test',
      [],
      ['file_extensions' => 'txt'],
    );

    // Add an image field.
    FieldStorageConfig::create([
      'type' => 'image',
      'entity_type' => 'entity_test',
      'field_name' => 'image',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'field_name' => 'image',
      'label' => 'Image',
    ])->save();

    // Add a body field.
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

    // Add a text format that supports CKEditor embedded images.
    FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
    ])->setFilterConfig('filter_html', [
      'status' => TRUE,
      'settings' => [
        'allowed_html' => '<img src alt data-entity-uuid data-entity-type height width>',
      ],
    ])->save();

    $this->config('entity_usage.settings')
      ->set('track_enabled_source_entity_types', ['entity_test'])
      ->set('track_enabled_target_entity_types', ['file', 'image'])
      ->set('track_enabled_plugins', ['entity_reference', 'ckeditor_image'])
      ->save();
  }

  /**
   * Tests tracking files and images.
   */
  public function testFile(): void {
    $images = $this->getTestFiles('image');
    $embedded_image_file = File::create(['uri' => $images[0]->uri]);
    $embedded_image_file->save();
    $embedded_image_url = $this->container->get('file_url_generator')->generateString($images[0]->uri);
    $embedded_image_markup = '<img src="' . $embedded_image_url . '" data-entity-type="file" data-entity-uuid="' . $embedded_image_file->uuid() . '" />';

    $entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
      'file' => File::create([
        'uri' => $this->generateFile($this->randomMachineName(), 1, 1, 'text'),
      ]),
      'image' => File::create([
        'uri' => $images[1]->uri,
      ]),
      'text' => [
        'format' => 'basic_html',
        'value' => $embedded_image_markup,
      ],
    ]);
    $entity->save();

    $this->assertSame([
      'file' => [
        $entity->get('image')->target_id => [
          [
            'method' => 'entity_reference',
            'field_name' => 'image',
            'count' => '1',
          ],
        ],
        $entity->get('file')->target_id => [
          [
            'method' => 'entity_reference',
            'field_name' => 'file',
            'count' => '1',
          ],
        ],
        $embedded_image_file->id() => [
          [
            'method' => 'ckeditor_image',
            'field_name' => 'text',
            'count' => '1',
          ],
        ],
      ],
    ], $this->container->get('entity_usage.usage')->listTargets($entity));
  }

}
