<?php

namespace Drupal\Tests\media_entity\Functional;

use Drupal\Core\Config\Entity\Query\QueryFactory;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests the media_entity to media update.
 *
 * @group media_entity
 * @group legacy
 */
class CoreMediaUpdatePath8dot7Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    // @todo display.default.display_options.fields.media_bulk_form.action_title
    //   has no schema.
    'views.view.media',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../fixtures/drupal-8.7.0-media-entity.php.gz',
    ];
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
    $this->assertArrayHasKey('media', $this->config('core.extension')->get('module'));

    $this->drupalGet('/admin/structure/media/manage/document');
    $assert->statusCodeEquals(200);
    $assert->fieldValueEquals('source', 'generic');
    $assert->pageTextContains('Generic media field is used to store the essential information');

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

    foreach (['document'] as $type) {
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
