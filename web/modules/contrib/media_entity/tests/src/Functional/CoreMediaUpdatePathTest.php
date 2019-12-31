<?php

namespace Drupal\Tests\media_entity\Functional;

use Drupal\Core\Config\Entity\Query\QueryFactory;
use Drupal\Core\Database\Database;
use Drupal\Core\Serialization\Yaml;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the media_entity to media update.
 *
 * @group media_entity
 * @group legacy
 */
class CoreMediaUpdatePathTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // @todo Work out how to add source_configuration.gather_exif schema. See
    //   https://www.drupal.org/project/media_entity_image_exif/issues/3062563.
    'media.type.image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../fixtures/drupal-8.4.0-media-entity.php.gz',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->config('views.view.media')
      ->clear('display.default.display_options.fields.media_bulk_form')
      ->save();
  }

  /**
   * Tests the media_entity to media update.
   */
  public function testUpdatePath() {
    $icon_base_uri = $this->config('media_entity.settings')->get('icon_base');

    $this->runUpdates();
    $assert = $this->assertSession();

    // Ensure the full view mode was created.
    $view_mode_storage = $this->container->get('entity_type.manager')->getStorage('entity_view_mode');
    $this->assertNotNull($view_mode_storage->load('media.full'));

    // As with all translatable, versionable content entity types, media
    // entities should have the revision_translation_affected base field.
    // This may have been created during the update path by system_update_8402,
    // so we should check for it here.
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager */
    $field_manager = $this->container->get('entity_field.manager');
    $this->assertArrayHasKey('revision_translation_affected', $field_manager->getBaseFieldDefinitions('media'));
    // Ensure that media fields that were created during module install are
    // deleted properly.
    $base_field_definitions = $field_manager->getBaseFieldDefinitions('media');
    $field_map = $field_manager->getFieldMap();
    $this->assertArrayNotHasKey('field_media_document', $base_field_definitions);
    $this->assertArrayNotHasKey('field_media_document', $field_map['media']);
    $this->assertArrayNotHasKey('field_media_oembed_video', $base_field_definitions);
    $this->assertArrayNotHasKey('field_media_oembed_video', $field_map['media']);
    $this->assertArrayNotHasKey('field_media_video_file', $base_field_definitions);
    $this->assertArrayNotHasKey('field_media_video_file', $field_map['media']);

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/admin/modules');
    $assert->checkboxNotChecked('modules[media_entity_document][enable]');
    $assert->checkboxNotChecked('modules[media_entity_image][enable]');
    $assert->checkboxNotChecked('modules[media_entity][enable]');
    $assert->checkboxChecked('modules[media_entity_generic][enable]');
    // Media is not currently displayed on the Modules page.
    $this->assertArrayHasKey('media', $this->config('core.extension')->get('module'));

    $this->drupalGet('/admin/structure/media/manage/file');
    $assert->statusCodeEquals(200);
    $assert->fieldValueEquals('source', 'file');
    $assert->pageTextContains('File field is used to store the essential information');

    $this->drupalGet('/admin/structure/media/manage/image');
    $assert->statusCodeEquals(200);
    $assert->fieldValueEquals('source', 'image');
    $assert->pageTextContains('Image field is used to store the essential information');

    $this->drupalGet('/admin/structure/media/manage/generic');
    $assert->statusCodeEquals(200);
    $assert->fieldValueEquals('source', 'generic');
    $assert->pageTextContains('Generic media field is used to store the essential information');

    $this->assertFrontPageMedia('Image 3', 'main img');
    $this->assertFrontPageMedia('Generic 1', 'main img[src *= "/media-icons/generic/generic.png"]');
    $this->assertFrontPageMedia('File 2', 'main img[src *= "/media-icons/generic/document.png"]');
    $this->assertFrontPageMedia('File 3', 'main img[src *= "/media-icons/generic/document.png"]');
    $this->assertFrontPageMedia('Image 1', 'main img');
    $this->assertFrontPageMedia('Generic 3', 'main img[src *= "/media-icons/generic/generic.png"]');

    // Assert that Media Entity's config is migrated.
    $this->assertTrue($this->config('media_entity.settings')->isNew());
    $this->assertEquals($icon_base_uri, $this->config('media.settings')->get('icon_base_uri'));
    $this->assertEmpty(
      $this->container->get('config.factory')->listAll('media_entity.bundle')
    );

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container
      ->get('entity_type.manager')
      ->getStorage('media_type');

    foreach (['file', 'image', 'generic'] as $type) {
      $config = $this->config("media.type.$type");
      $this->assertFalse($config->isNew());
      $this->assertNull($config->get('type'));
      $this->assertNull($config->get('type_configuration'));
      $this->assertInternalType('string', $config->get('source'));
      $this->assertInternalType('array', $config->get('source_configuration'));
      $this->assertInternalType('string', $config->get('source_configuration.source_field'));

      // Ensure that the media type can be queried by UUID.
      $uuid = $config->get('uuid');
      $this->assertNotEmpty($uuid);
      $result = $storage->getQuery()->condition('uuid', $uuid)->execute();
      $this->assertEquals($result[$type], $type);
    }

    // The UUID map for legacy media bundles should be cleared out.
    $old_uuid_map = $this->container
      ->get('keyvalue')
      ->get(QueryFactory::CONFIG_LOOKUP_PREFIX . 'media_bundle')
      ->getAll();
    $this->assertEmpty($old_uuid_map);

    // Ensure media_entity is removed from the update schema list.
    $media_entity_schema = $this->container
      ->get('keyvalue')
      ->get('system.schema')
      ->get('media_entity');
    $this->assertEmpty($media_entity_schema);

    // Test the installed entity definition use core's Media entity and not the
    // fake definition installed in media_entity_update_8200(). Note that
    // \Drupal\FunctionalTests\Update\UpdatePathTestBase::runUpdates() ensures
    // there are no outstanding entity updates.
    $media_entity_type = \Drupal::entityDefinitionUpdateManager()->getEntityType('media');
    $this->assertSame('Drupal\media\Entity\Media', $media_entity_type->getClass());
    $this->assertSame('Drupal\media\MediaStorage', $media_entity_type->getStorageClass());
    $expected_keys = [
      'id' => 'mid',
      'revision' => 'vid',
      'bundle' => 'bundle',
      'label' => 'name',
      'langcode' => 'langcode',
      'uuid' => 'uuid',
      'published' => 'status',
      'owner' => 'uid',
      'default_langcode' => 'default_langcode',
      'revision_translation_affected' => 'revision_translation_affected',
    ];
    $this->assertSame($expected_keys, $media_entity_type->getKeys());
    $expected = [
      'revision_user' => 'revision_user',
      'revision_created' => 'revision_created',
      'revision_log_message' => 'revision_log_message',
      'revision_default' => 'revision_default',
    ];
    $this->assertSame($expected, $media_entity_type->getRevisionMetadataKeys());
  }

  /**
   * Test the upgrade path with an already created full view mode.
   */
  public function testWithFullViewMode() {
    $view_modes[] = Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/core.entity_view_mode.testfor2936425.yml'));

    $connection = Database::getConnection();
    $connection->insert('config')
      ->fields([
        'collection',
        'name',
        'data',
      ])
      ->values([
        'collection' => '',
        'name' => 'core.entity_view_mode.media.full',
        'data' => serialize($view_modes[0]),
      ])
      ->execute();

    $this->runUpdates();

    // Ensure the full view mode didn't changed.
    $view_mode_storage = $this->container->get('entity_type.manager')->getStorage('entity_view_mode');
    $this->assertEqual($view_mode_storage->load('media.full')->uuid(), 'ee7c230c-337b-4e8f-8600-d65bfd34f172');
    $this->assertArraySubset($this->config('core.entity_view_mode.media.full')->get('dependencies'), ['module' => ['media']]);
  }

  /**
   * Test that field overrides are also renamed.
   */
  public function testFieldOverrides() {
    $field_overrides = Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/core.base_field_override.testfor2933338.yml'));
    $connection = Database::getConnection();
    $connection->insert('config')
      ->fields([
        'collection',
        'name',
        'data',
      ])
      ->values([
        'collection' => '',
        'name' => 'core.base_field_override.media.image.revision_log',
        'data' => serialize($field_overrides),
      ])
      ->execute();

    $this->runUpdates();

    $this->assertTrue($this->config('core.base_field_override.media.image.revision_log')->isNew());
    $new_field_config = $this->config('core.base_field_override.media.image.revision_log_message');
    $this->assertFalse($new_field_config->isNew());
    $this->assertArraySubset($new_field_config->get('dependencies'), ['config' => ['media.type.image']]);
    $this->assertSame('media.image.revision_log_message', $new_field_config->get('id'));
    $this->assertSame('revision_log_message', $new_field_config->get('field_name'));
  }

  /**
   * Clicks a link on the front page and checks for some selectors.
   *
   * @param string $link
   *   Link to click on the frontpage.
   * @param array|string $assert_selectors
   *   CSS selectors to check for their existence.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function assertFrontPageMedia($link, $assert_selectors) {
    $this->drupalGet('<front>');
    $this->clickLink($link);

    $assert = $this->assertSession();
    foreach ((array) $assert_selectors as $selector) {
      $assert->elementExists('css', $selector);
    }
  }

}
