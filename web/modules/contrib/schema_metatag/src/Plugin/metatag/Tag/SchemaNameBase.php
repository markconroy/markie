<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;
use Drupal\schema_metatag\SchemaMetatagManager;
use Drupal\schema_metatag\SchemaMetatagTestTagInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * All Schema.org tags should extend this class.
 */
class SchemaNameBase extends MetaNameBase implements SchemaMetatagTestTagInterface, ContainerFactoryPluginInterface {

  /**
   * The schemaMetatagManager service.
   *
   * @var \Drupal\schema_metatag\schemaMetatagManager
   */
  protected $schemaMetatagManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->setSchemaMetatagManager($container->get('schema_metatag.schema_metatag_manager'));
    return $instance;
  }

  /**
   * Sets schemaMetatagManager service.
   *
   * @param \Drupal\schema_metatag\SchemaMetatagManager $schemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  public function setSchemaMetatagManager(SchemaMetatagManager $schemaMetatagManager) {
    $this->schemaMetatagManager = $schemaMetatagManager;
  }

  /**
   * Return the SchemaMetatagManager.
   *
   * @return \Drupal\schema_metatag\SchemaMetatagManager
   *   The Schema Metatag Manager service.
   */
  protected function schemaMetatagManager() {
    return $this->schemaMetatagManager;
  }

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

    $value = $this->schemaMetatagManager()->unserialize($this->value());

    // If this is a complex array of value, process the array.
    if (is_array($value)) {

      // Clean out empty values.
      $value = $this->schemaMetatagManager()->arrayTrim($value);
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
    $this->value = $this->schemaMetatagManager()->serialize($value);
  }

  /**
   * {@inheritdoc}
   */
  public static function pivotItem($array) {
    // See if any nested items need to be pivoted.
    // If pivot is set to 0, it would have been removed as an empty value.
    if (array_key_exists('pivot', $array)) {
      unset($array['pivot']);
      /** @var \Drupal\schema_metatag\SchemaMetatagManagerInterface $schemaMetatagManager */
      $schemaMetatagManager = \Drupal::service('schema_metatag.schema_metatag_manager');
      $array = $schemaMetatagManager->pivot($array);
    }
    foreach ($array as &$value) {
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
      $value = $this->schemaMetatagManager()->explode($value);
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
   * {@inheritdoc}
   */
  public static function outputValue($input_value) {
    return $input_value;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return static::testDefaultValue(2, ' ');
  }

  /**
   * {@inheritdoc}
   */
  public static function processedTestValue($items) {
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function processTestExplodeValue($items) {
    if (!is_array($items)) {
      // Call this value statically for static test value.
      $items = SchemaMetatagManager::explode($items);
      // Clean out any empty values that might have been added by explode().
      if (is_array($items)) {
        array_filter($items);
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public static function testDefaultValue($count = NULL, $delimiter = NULL) {
    $items = [];
    $min = 1;
    $max = isset($count) ? $count : 2;
    $delimiter = isset($delimiter) ? $delimiter : ' ';
    for ($i = $min; $i <= $max; $i++) {
      // Call this value statically for static test value.
      $items[] = SchemaMetatagManager::randomMachineName();
    }
    return implode($delimiter, $items);
  }

}
