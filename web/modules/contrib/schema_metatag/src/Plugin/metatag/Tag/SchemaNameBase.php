<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * All Schema.org tags should extend this class.
 */
abstract class SchemaNameBase extends MetaNameBase {

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
    if (empty($value)) {
      return '';
    }
    // If this is a complex array of value, process the array.
    elseif (is_array($value)) {

      // Clean out empty values.
      $value = array_filter($value);

      // If the item is an array of values,
      // walk the array and process the values.
      array_walk_recursive($value, 'static::processItem');

      // See if any nested items need to be pivoted.
      // If pivot is set to 0, it would have been removed as an empty value.
      if (array_key_exists('pivot', $value)) {
        unset($value['pivot']);
        $value = SchemaMetatagManager::pivot($value);
      }

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
  protected function processItem(&$value, $key = 0) {
    // Parse out the image URL, if needed.
    $value = $this->parseImageUrlValue($value);

    $value = trim($value);

    // If tag must be secure, convert all http:// to https://.
    if ($this->secure() && strpos($value, 'http://') !== FALSE) {
      $value = str_replace('http://', 'https://', $value);
    }

    $value = $this->multiple() ? SchemaMetatagManager::explode($value) : $value;
  }

  /**
   * Parse the image url out of image markup.
   *
   * A copy of the base method of the same name, but where $value is passed
   * in instead of assumed to be $this->value().
   */
  protected function parseImageUrlValue($value) {

    // If this contains embedded image tags, extract the image URLs.
    if ($this->type() === 'image') {
      // If image tag src is relative (starts with /), convert to an absolute
      // link.
      global $base_root;
      if (strpos($value, '<img src="/') !== FALSE) {
        $value = str_replace('<img src="/', '<img src="' . $base_root . '/', $value);
      }

      if (strip_tags($value) != $value) {
        if ($this->multiple()) {
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
   * Provide a test value for the property that will validate.
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
