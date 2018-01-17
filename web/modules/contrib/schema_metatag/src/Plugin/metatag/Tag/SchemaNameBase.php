<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use \Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
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
   * Add group info and identify tags that use the schema.org definitions.
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
      array_walk_recursive($value, 'self::process_item');

      // See if any nested items need to be pivoted.
      // If pivot is set to 0, it would have been removed as an empty value.
      if (array_key_exists('pivot', $value)) {
        unset($value['pivot']);
        $value = SchemaMetatagManager::pivot($value);
      }

    }
    // Process a simple string.
    else {
     $this->process_item($value);
    }
    $output = [
      '#tag' => 'meta',
      '#attributes' => [
        'name' => $this->name,
        'content' => $value,
        'group' => $this->group,
        'schema_metatag' => TRUE,
      ]
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
   * @inherit
   */
  protected function process_item(&$value, $key = 0) {

    // Parse out the image URL, if needed.
    $value = $this->parseImageURLValue($value);

    $value = trim($value);

    // If tag must be secure, convert all http:// to https://.
    if ($this->secure() && strpos($value, 'http://') !== FALSE) {
      $value = str_replace('http://', 'https://', $value);
    }

    $value = $this->multiple() ? SchemaMetatagManager::explode($value) : $value;
  }

  /**
   * A copy of the base method of the same name, but where $value is passed
   * in instead of assumed to be $this->value().
   */
  protected function parseImageURLValue($value) {

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
}
