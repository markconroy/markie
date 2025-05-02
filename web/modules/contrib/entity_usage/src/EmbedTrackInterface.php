<?php

namespace Drupal\entity_usage;

/**
 * Defines the interface for tracking plugins that embed entities in WYSIWYG.
 */
interface EmbedTrackInterface extends EntityUsageTrackInterface {

  /**
   * Prefix to indicate that the entities existence has already been checked.
   */
  const VALID_ENTITY_ID_PREFIX = 'CHECKED|';

  /**
   * Parse an HTML snippet looking for embedded entities.
   *
   * @param string $text
   *   The partial (X)HTML snippet to load. Invalid markup will be corrected on
   *   import.
   *
   * @return array<string, string>
   *   An array of all embedded entities found, where keys are the uuids and the
   *   values are the entity types. If the entity type is prefixed with
   *   \Drupal\entity_usage\EmbedTrackInterface::VALID_ENTITY_ID_PREFIX then the
   *   existence of the entity has already been checked and the value has a
   *   suffix of '|$entity_ID'.
   */
  public function parseEntitiesFromText($text);

}
