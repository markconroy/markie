<?php

namespace Drupal\Tests\schema_metatag\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\schema_metatag\SchemaMetatagManager;

/**
 * @coversDefaultClass \Drupal\schema_metatag\SchemaMetatagManager
 *
 * @group schema_metatag
 * @group schema_metatag_base
 */
class SchemaMetatagManagerTest extends UnitTestCase {

  /**
   * @covers ::pivot
   * @dataProvider pivotData
   */
  public function testPivot($original, $desired) {
    $processed = SchemaMetatagManager::pivot($original);
    $this->assertEquals($processed, $desired);
  }

  /**
   * @covers ::explode
   * @dataProvider stringData
   */
  public function testExplode($original, $desired) {
    $processed = SchemaMetatagManager::explode($original);
    $this->assertEquals($processed, $desired);
  }

  /**
   * @covers ::arrayTrim
   * @dataProvider arrayData
   */
  public function testArrayTrim($tests, $original, $original_serialized, $desired, $desired_serialized) {
    if (!in_array('arraytrim', $tests)) {
      $this->assertTrue(TRUE);
      return;
    }
    $processed = SchemaMetatagManager::arrayTrim($original);
    $this->assertEquals($processed, $desired);
  }

  /**
   * @covers ::unserialize
   * @dataProvider arrayData
   */
  public function testUnserialize($tests, $original, $original_serialized, $desired, $desired_serialized) {
    if (!in_array('unserialize', $tests)) {
      $this->assertTrue(TRUE);
      return;
    }
    $processed = SchemaMetatagManager::unserialize($original_serialized);
    $this->assertEquals($processed, $desired);
  }

  /**
   * @covers ::serialize
   * @dataProvider arrayData
   */
  public function testSerialize($tests, $original, $original_serialized, $desired, $desired_serialized) {
    if (!in_array('serialize', $tests)) {
      $this->assertTrue(TRUE);
      return;
    }
    $processed = SchemaMetatagManager::serialize($original);
    $this->assertEquals($processed, $desired_serialized);
  }

  /**
   * @covers ::recomputeSerializedLength
   *
   * @dataProvider arrayData
   */
  public function testRecomputeSerializedLength($tests, $original, $original_serialized, $desired, $desired_serialized) {
    if (!in_array('recompute', $tests)) {
      $this->assertTrue(TRUE);
      return;
    }
    $replaced = str_replace('Organization', 'ReallyBigOrganization', $original_serialized);
    $processed = SchemaMetatagManager::recomputeSerializedLength($replaced);
    $unserialized = unserialize($processed);
    $this->assertTrue(is_array($unserialized));
    $this->assertTrue(array_key_exists('ReallyBigOrganization', $unserialized['@type']));
  }

  /**
   * Provides pivot data.
   *
   * @return array
   */
  public function pivotData() {
    $values = [
      'Simple pivot' => [
        [
          '@type' => 'Person',
          'name' => 'George',
          'Tags' => [
            'First',
            'Second',
            'Third',
          ],
        ],
        [
          0 => ['@type' => 'Person', 'name' => 'George', 'Tags' => 'First'],
          1 => ['@type' => 'Person', 'name' => 'George', 'Tags' => 'Second'],
          2 => ['@type' => 'Person', 'name' => 'George', 'Tags' => 'Third'],
        ],
      ],
    ];
    return $values;
  }

  /**
   * Provides array data.
   *
   * @return array
   */
  public function arrayData() {
    $values['Dirty input'] = [
      [
        'explode',
      ],
      [
        '@type' => ' Organization',
        'name' => 'test ',
        'description' => 'more text',
      ],
      'a:1:{s:5:"@type";a:1:{s:13:" Organization";a:2:{s:4:"name";s:5:"test ";s:11:"description";s:9:"more text";}}}',
      [
        '@type' => 'Organization',
        'name' => 'test',
        'description' => 'more text',
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:2:{s:4:"name";s:4:"test";s:11:"description";s:9:"more text";}}}',
    ];
    $values['Nested array'] = [
      [
        'arraytrim',
        'serialize',
        'unserialize',
        'explode',
        'recompute',
      ],
      [
        '@type' => [
          'Organization' => [
            'name' => 'test',
            'description' => 'more text',
          ],
        ],
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:2:{s:4:"name";s:4:"test";s:11:"description";s:9:"more text";}}}',
      [
        '@type' => [
          'Organization' => [
            'name' => 'test',
            'description' => 'more text',
          ],
        ],
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:2:{s:4:"name";s:4:"test";s:11:"description";s:9:"more text";}}}',
    ];
    $values['Empty array'] = [
      [
        'arraytrim',
        'serialize',
        'unserialize',
        'explode',
      ],
      [
        '@type' => [
          'Organization' => [
            'name' => '',
            'description' => '',
          ],
        ],
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:2:{s:4:"name";s:0:"";s:11:"description";s:0:"";}}}',
      [],
      '',
    ];
    $values['Empty parts'] = [
      ['recompute'],
      [
        '@type' => [
          'Organization' => [
            'name' => 'test',
            'description' => '',
          ],
        ],
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:2:{s:4:"name";s:4:"test";s:11:"description";s:0:"";}}}',
      [
        '@type' => [
          'Organization' => [
            'description' => 'more text',
          ],
        ],
      ],
      'a:1:{s:5:"@type";a:1:{s:12:"Organization";a:1:{s:11:"description";s:9:"more text";}}}',
    ];
    return $values;
  }

  /**
   * Provides string data.
   *
   * @return array
   */
  public function stringData() {
    $values = [
      'Comma separated' => [
        'First,Second,Third',
        ['First', 'Second', 'Third'],
      ],
      'Needs trimming' => [
        ' First, Second , Third',
        ['First', 'Second', 'Third'],
      ],
    ];
    return $values;
  }

}
