<?php

namespace Drupal\schema_metatag;

/**
 * Interface SchemaMetatagTestTagInterface.
 *
 * Methods that provide test values for SchemaNameBase and its derivatives.
 *
 * @package Drupal\schema_metatag
 */
interface SchemaMetatagTestTagInterface {

  /**
   * Provide a test input value for the property that will validate.
   *
   * Tags like @type that contain values other than simple strings, for
   * instance a list of allowed options, should extend this method and return
   * a valid value.
   *
   * Value must be static so the test can retrieve the value without
   * instantiating the class.
   *
   * @return mixed
   *   Return the test value, either a string or array, depending on the
   *   property.
   */
  public static function testValue();

  /**
   * Provide a test output value for the input value.
   *
   * Tags that return values in a different format than the input, like
   * values that are exploded, should extend this method and return
   * a valid value.
   *
   * Value must be static so the test can retrieve the value without
   * instantiating the class.
   *
   * @param mixed $items
   *   The input value, either a string or an array.
   *
   * @return mixed
   *   Return the correct output value.
   */
  public static function processedTestValue($items);

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
  public static function outputValue($input_value);

  /**
   * Explode a test value.
   *
   * For test values, emulates the extra processing a multiple value would get.
   *
   * Value must be static so the test can retrieve the value without
   * instantiating the class.
   *
   * @param mixed $items
   *   The input value, either a string or an array.
   *
   * @return mixed
   *   Return the correct output value.
   */
  public static function processTestExplodeValue($items);

  /**
   * Provide a random test value.
   *
   * A helper function to create a random test value. Use the delimiter to
   * create comma-separated values, or a few "words" separated by spaces.
   *
   * Value must be static so the test can retrieve the value without
   * instantiating the class.
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
  public static function testDefaultValue($count = NULL, $delimiter = NULL);

}
