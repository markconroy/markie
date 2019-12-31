<?php

namespace Drupal\media_entity_generic\Plugin\media\Source;

use Drupal\media\MediaSourceBase;

/**
 * Generic media source.
 *
 * @MediaSource(
 *   id = "generic",
 *   label = @Translation("Generic media"),
 *   description = @Translation("Generic media type."),
 *   allowed_field_types = {"string"},
 *   default_thumbnail_filename = "generic.png"
 * )
 */
class Generic extends MediaSourceBase {

  /**
   * {@inheritdoc}
   */
  public function getMetadataAttributes() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function createSourceFieldStorage() {
    return parent::createSourceFieldStorage()->set('custom_storage', TRUE);
  }

}
