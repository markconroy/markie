<?php

namespace Drupal\schema_metatag;

/**
 * Interface SchemaMetatagManagerInterface.
 *
 * @package Drupal\schema_metatag
 */
interface SchemaMetatagManagerInterface {

  /**
   * Parse tags added by Schema Metatag into JsonLD array.
   *
   * @param array $elements
   *   Array of Metatag values, as formatted for the head of a page.
   *
   * @return array
   *   Array of Schema metatag tags, ready to be turned into JSON LD.
   */
  public static function parseJsonld(array &$elements);

  /**
   * Convert a metatags-style data array to JSON LD.
   *
   * @param array $items
   *   Array of Schema metatag tags, ready to be turned into JSON LD.
   *
   * @return string
   *   Json-encoded representation of the structured data.
   */
  public static function encodeJsonld(array $items);

  /**
   * Create the JSON LD render array.
   */
  public static function renderArrayJsonLd($jsonld);

  /**
   * Render JSON LD for a specific entity.
   *
   * Useful to pass to a decoupled front end, for instance.
   */
  public static function getRenderedJsonld($entity = NULL, $entity_type = NULL);

  /**
   * Pivot values.
   *
   * Complex serialized value that might contain multiple
   * values. In this case we have to pivot the results.
   */
  public static function pivot($content);

  /**
   * Explode values if this is a multiple value field.
   */
  public static function explode($value);

  /**
   * Wrapper for serialize to prevent errors.
   */
  public static function serialize($value);

  /**
   * Wrapper for unserialize to prevent errors.
   */
  public static function unserialize($value);

  /**
   * Check if a value looks like a serialized array.
   */
  public static function isSerialized($value);

  /**
   * Remove empty values from a nested array.
   *
   * If the result is an empty array, the nested array is completely empty.
   */
  public static function arrayTrim($input);

  /**
   * Update serialized item length computations.
   *
   * Prevent unserialization error if token replacements are different lengths
   * than the original tokens.
   */
  public static function recomputeSerializedLength($value);

}
