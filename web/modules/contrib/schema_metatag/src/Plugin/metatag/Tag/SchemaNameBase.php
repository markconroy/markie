<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * All Schema.org tags should extend this class.
 */
class SchemaNameBase extends MetaNameBase {

  /**
   * The #states base visibility selector for this element.
   */
  protected function visibilitySelector() {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function output() {

    $value = SchemaMetatagManager::unserialize($this->value());

    // If this is a complex array of value, process the array.
    if (is_array($value)) {

      // Clean out empty values.
      $value = SchemaMetatagManager::arrayTrim($value);
    }

    if (empty($value)) {
      return '';
    }
    // If this is a complex array of value, process the array.
    elseif (is_array($value)) {

      // If the item is an array of values,
      // walk the array and process the values.
      array_walk_recursive($value, 'static::processItem');

      // Recursively pivot each branch of the array.
      $value = static::pivotItem($value);

    }
    // Process a simple string.
    else {
      $this->processItem($value);
    }
    $output = [
      '#tag' => 'meta',
      '#attributes' => [
        'name' => $this->name,
        'content' => static::outputValue($value),
        'group' => $this->group,
        'schema_metatag' => TRUE,
      ],
    ];

    return $output;
  }

  /**
   * The serialized value for the metatag.
   *
   * Metatag expects a string value, so use the serialized value
   * without unserializing it. Manually unserialize it when needed.
   */
  public function value() {
    return $this->value;
  }

  /**
   * Metatag expects a string value, so serialize any array of values.
   */
  public function setValue($value) {
    $this->value = SchemaMetatagManager::serialize($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function pivotItem($array) {
    // See if any nested items need to be pivoted.
    // If pivot is set to 0, it would have been removed as an empty value.
    if (array_key_exists('pivot', $array)) {
      unset($array['pivot']);
      $array = SchemaMetatagManager::pivot($array);
    }
    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        $value = static::pivotItem($value);
      }
    }
    return $array;
  }

  /**
   * Nested elements that cannot be exploded.
   *
   * @return array
   *   Array of keys that might contain commas, or otherwise cannot be exploded.
   */
  protected function neverExplode() {
    return [
      'streetAddress',
      'reviewBody',
      'recipeInstructions',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function processItem(&$value, $key = 0) {

    $explode = $key === 0 ? $this->multiple() : !in_array($key, $this->neverExplode());

    // Parse out the image URL, if needed.
    $value = $this->parseImageUrlValue($value, $explode);

    $value = trim($value);

    // If tag must be secure, convert all http:// to https://.
    if ($this->secure() && strpos($value, 'http://') !== FALSE) {
      $value = str_replace('http://', 'https://', $value);
    }
    if ($explode) {
      $value = SchemaMetatagManager::explode($value);
      // Clean out any empty values that might have been added by explode().
      if (is_array($value)) {
        $value = array_filter($value);
      }
    }
  }

  /**
   * Parse the image url out of image markup.
   *
   * A copy of the base method of the same name, but where $value is passed
   * in instead of assumed to be $this->value().
   */
  protected function parseImageUrlValue($value, $explode) {

    // If this contains embedded image tags, extract the image URLs.
    if ($this->type() === 'image') {
      // If image tag src is relative (starts with /), convert to an absolute
      // link.
      global $base_root;
      if (strpos($value, '<img src="/') !== FALSE) {
        $value = str_replace('<img src="/', '<img src="' . $base_root . '/', $value);
      }

      if (strip_tags($value) != $value) {
        if ($explode) {
          $values = explode(',', $value);
        }
        else {
          $values = [$value];
        }

        // Check through the value(s) to see if there are any image tags.
        foreach ($values as $key => $val) {
          $matches = [];
          preg_match('/src="([^"]*)"/', $val, $matches);
          if (!empty($matches[1])) {
            $values[$key] = $matches[1];
          }
        }
        $value = implode(',', $values);

        // Remove any HTML tags that might remain.
        $value = strip_tags($value);
      }
    }

    return $value;
  }

  /**
   * Transform input value to its display output.
   *
   * Tags that need to transform the output to something different than the
   * stored value should extend this method and do the transformation here.
   *
   * @param mixed $input_value
   *   Input value, could be either a string or array. This will be the
   *   unserialized value stored in the tag configuration, after token
   *   replacement.
   *
   * @return mixed
   *   Return the (possibly expanded) value which will be rendered in JSON-LD.
   */
  public static function outputValue($input_value) {
    return $input_value;
  }

  /**
   * Provide a test input value for the property that will validate.
   *
   * Tags like @type that contain values other than simple strings, for
   * instance a list of allowed options, should extend this method and return
   * a valid value.
   *
   * @return mixed
   *   Return the test value, either a string or array, depending on the
   *   property.
   */
  public static function testValue() {
    return static::testDefaultValue(2, ' ');
  }

  /**
   * Provide a test output value for the input value.
   *
   * Tags that return values in a different format than the input, like
   * values that are exploded, should extend this method and return
   * a valid value.
   *
   * @param mixed $items
   *   The input value, either a string or an array.
   *
   * @return mixed
   *   Return the correct output value.
   */
  public static function processedTestValue($items) {
    return $items;
  }

  /**
   * Explode a test value.
   *
   * For test values, emulates the extra processing a multiple value would get.
   *
   * @param array $items
   *   The input value, either a string or an array.
   *
   * @return mixed
   *   Return the correct output value.
   */
  public static function processTestExplodeValue($items) {
    if (!is_array($items)) {
      $items = SchemaMetatagManager::explode($items);
      // Clean out any empty values that might have been added by explode().
      if (is_array($items)) {
        $value = array_filter($items);
      }
    }
    return $items;
  }

  /**
   * Provide a random test value.
   *
   * A helper function to create a random test value. Use the delimiter to
   * create comma-separated values, or a few "words" separated by spaces.
   *
   * @param int $count
   *   Number of "words".
   * @param int $delimiter
   *   Delimiter used to connect "words".
   *
   * @return mixed
   *   Return the test value, either a string or array, depending on the
   *   property.
   */
  public static function testDefaultValue($count = NULL, $delimiter = NULL) {
    $items = [];
    $min = 1;
    $max = isset($count) ? $count : 2;
    $delimiter = isset($delimiter) ? $delimiter : ' ';
    for ($i = $min; $i <= $max; $i++) {
      $items[] = SchemaMetatagManager::randomMachineName();
    }
    return implode($delimiter, $items);
  }

}
