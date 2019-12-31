<?php

namespace Drupal\Tests\vem_embed_media\Functional;

use Drupal\media\Entity\MediaType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Tests the VEM to OEmbed migration.
 *
 * @group vem_embed_media
 */
class oEmbedUpdateTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['vem_migrate_oembed'];

  /**
   * Tests the VEM to OEmbed migration.
   */
  public function testOEmbedUpdate() {

    $mediaType = $this->createMediaType('video_embed_field');
    $this->assertEqual($mediaType->getSource()->getPluginId(), 'video_embed_field');

    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType);
    $this->assertEqual($sourceField->getType(), 'video_embed_field');

    $formDisplay = entity_get_form_display('media', $mediaType->id(), 'default');
    $formField = $formDisplay->getComponent($sourceField->getName());

    $this->assertEqual($formField['type'], 'video_embed_field_textfield');

    /** @var \Drupal\vem_migrate_oembed\VemMigrate $vemService */
    $vemService = \Drupal::service('vem_migrate_oembed.migrate');
    $vemService->migrate();

    /** @var \Drupal\media\Entity\MediaType $mediaType */
    $mediaType = MediaType::load($mediaType->id());
    $this->assertEqual($mediaType->getSource()->getPluginId(), 'oembed:video');

    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType);
    $this->assertEqual($sourceField->getType(), 'string');

    $formDisplay = entity_get_form_display('media', $mediaType->id(), 'default');
    $formField = $formDisplay->getComponent($sourceField->getName());

    $this->assertEqual($formField['type'], 'oembed_textfield');
  }

}
