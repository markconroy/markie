<?php

namespace Drupal\Tests\schema_metatag\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class to test all of the meta tags that are in a specific module.
 */
abstract class SchemaMetatagTagsTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // This is needed for the 'access content' permission.
    'node',

    // Dependencies.
    'token',
    'metatag',

    // This module.
    'schema_metatag',
  ];

  /**
   * The name of the module being tested.
   *
   * @var string
   */
  public $moduleName = '';

  /**
   * The namespace of the tags which will be tested.
   *
   * @var string
   */
  public $schemaTagsNamespace = '';

  /**
   * All of the individual tags which will be tested.
   *
   * @var array
   */
  public $schemaTags = [];

  /**
   * Convert the tag_name into the camelCase key used in the JSON array.
   *
   * @param string $tag_name
   *   The name of the tag.
   *
   * @return string
   *   The key used in the JSON array for this tag.
   */
  public function getKey($tag_name) {
    $replace = [
      '_type' => '_@type',
      '_id' => '_@id',
    ];
    $key = strtr($tag_name, $replace);
    $key = str_replace($this->moduleName . '_', '', $key);
    $parts = explode('_', $key);
    foreach ($parts as $i => $part) {
      $parts[$i] = $i > 0 ? ucfirst($part) : $part;
    }
    $key = implode($parts);
    return $key;
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Initiate session with a user who can manage metatags and access content.
    $permissions = [
      'administer site configuration',
      'administer meta tags',
      'access content',
    ];
    $account = $this->drupalCreateUser($permissions);
    $this->drupalLogin($account);

    // Create a content type to test with.
    $this->createContentType(['type' => 'page']);
    $node = $this->drupalCreateNode([
      'title' => t('Node 1!'),
      'type' => 'page',
      'promote' => 1,
    ]);

    // Make sure the home page is a valid route in case we want to test it.
    $this->config('system.site')->set('page.front', '/node')->save();
    $this->clear();
  }

  /**
   * Confirm that tags can be saved and that the output of each tag is correct.
   */
  public function testTagsInputOutput() {

    if (empty($this->schemaTags) || empty($this->schemaTagsNamespace)) {
      $this->markTestSkipped('Not enough information to test.');
      return;
    }

    $paths = $this->getPaths();
    foreach ($paths as $item) {
      list($config_path, $rendered_path, $save_message) = $item;

      // Load the config page.
      $this->drupalGet($config_path);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->elementExists('xpath', '//input[@type="submit"][@value="Save"]');

      // Configure all the tag values and post the results.
      $expected_output_values = $raw_values = $form_values = [];
      foreach ($this->schemaTags as $tag_name => $class_name) {

        // Transform the tag_name to the camelCase key used in the form.
        $key = $this->getKey($tag_name);

        // Find the name of the class that defines this property, and use it to
        // identify a valid test value, and determine what the rendered output
        // should look like. Store the rendered value so we can compare it to
        // the output. Store the raw value so we can check that it exists in the
        // config form.
        $class = $this->schemaTagsNamespace . $class_name;
        $test_value = $class::testValue();
        $raw_values[$tag_name] = $test_value;
        $expected_output_values[$key] = $class::outputValue($test_value);

        // Rewrite the test values to match the way the form elements are
        // structured.
        // @TODO There is probably some way to write this as a recursive
        // function that will go more than three levels deep, but for now this
        // is enough.
        if (!is_array($test_value)) {
          $form_values[$tag_name] = $test_value;
        }
        else {
          foreach ($test_value as $key => $value) {
            if (is_array($value)) {
              foreach ($value as $key2 => $value2) {
                if (is_array($value2)) {
                  foreach ($value2 as $key3 => $value3) {
                    $keys = implode('][', [$key, $key2, $key3]);
                    $form_values[$tag_name . '[' . $keys . ']'] = $value3;
                  }
                }
                else {
                  $keys = implode('][', [$key, $key2]);
                  $form_values[$tag_name . '[' . $keys . ']'] = $value2;
                }
              }
            }
            else {
              $keys = implode('][', [$key]);
              $form_values[$tag_name . '[' . $keys . ']'] = $value;
            }
          }
        }
      }
      $this->drupalPostForm(NULL, $form_values, 'Save');
      $this->assertSession()->pageTextContains($save_message, 'Configuration successfully posted.');

      // Load the config page to confirm the settings got saved.
      $this->drupalGet($config_path);
      foreach ($this->schemaTags as $tag_name => $class) {
        // Check that simple string test values exist in the form to see that
        // form values were saved accurately. Don't try to recurse through all
        // arrays, more complicated values will be tested from the JSON output
        // they create.
        if (is_string($raw_values[$tag_name])) {
          $string = strtr('//*[@name=":tag_name"]', [':tag_name' => $tag_name]);
          $elements = $this->xpath($string);
          $value = count($elements) ? $elements[0]->getValue() : NULL;
          $this->assertEquals($value, $raw_values[$tag_name]);
        }
      }

      // Load the rendered page to see if the JSON-LD is displayed correctly.
      $this->drupalGet($rendered_path);
      $this->assertSession()->statusCodeEquals(200);

      // Make sure JSON-LD is present and can be decoded.
      $this->assertSession()->elementExists('xpath', '//script[@type="application/ld+json"]');
      $elements = $this->xpath('//script[@type="application/ld+json"]');
      $this->assertEquals(count($elements), 1);
      $json = json_decode($elements[0]->getHtml(), TRUE);
      $this->assertNotEmpty($json);
      $output_values = $json['@graph'][0];

      // Compare input and output values.
      foreach ($this->schemaTags as $tag_name => $class) {
        $key = $this->getKey($tag_name);
        $this->assertEquals($output_values[$key], $expected_output_values[$key]);
      }
    }

    $this->drupalLogout();
  }

  /**
   * Paths to test.
   *
   * Tags that need to be tested on other paths can extend this method.
   *
   * [$config_path, $rendered_path, $message]
   *
   * Examples:
   * // Global options.
   * [
   *   'admin/config/search/metatag/global',
   *   'somepath/that/must/exist',
   *   'Saved the Global Metatag defaults.',
   * ],
   * // The front page.
   * [
   *   'admin/config/search/metatag/front',
   *   '<front>',
   *   'Saved the Front page Metatag defaults.',
   * ],
   */
  public function getPaths() {
    return [
      // The node page.
      [
        'admin/config/search/metatag/node',
        'node/1',
        'Saved the Content Metatag defaults',
      ],
    ];
  }

  /**
   * A way to clear caches.
   */
  protected function clear() {
    $this->rebuildContainer();
  }

}
