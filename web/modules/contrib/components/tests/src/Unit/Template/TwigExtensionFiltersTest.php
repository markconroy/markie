<?php

namespace Drupal\Tests\components\Unit\Template;

use Drupal\components\Template\TwigExtension;
use Drupal\Tests\UnitTestCase;
use Twig\Extension\CoreExtension;

/**
 * @coversDefaultClass \Drupal\components\Template\TwigExtension
 * @group components
 */
class TwigExtensionFiltersTest extends UnitTestCase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $renderer;

  /**
   * The system under test.
   *
   * @var \Drupal\components\Template\TwigExtension
   */
  protected $systemUnderTest;

  /**
   * The Twig CoreExtension.
   *
   * @var \Twig\Extension\CoreExtension
   */
  protected $coreExtension;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->renderer = $this->createMock('\Drupal\Core\Render\RendererInterface');
    $this->systemUnderTest = new TwigExtension();

    // Load the Twig CoreExtension as its file contains static functions used by
    // TwigExtension.
    $this->coreExtension = new CoreExtension();
  }

  /**
   * Tests exceptions during set filter.
   *
   * @covers ::setFilter
   */
  public function testSetFilterException() {
    try {
      TwigExtension::setFilter('not-an-array', ['key' => 'value']);
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (\Exception $e) {
      $needle = 'The set filter only works with arrays or "Traversable", got "string" as first argument.';
      if (method_exists($this, 'assertStringContainsString')) {
        $this->assertStringContainsString($needle, $e->getMessage());
      }
      else {
        $this->assertContains($needle, $e->getMessage());
      }
    }
  }

  /**
   * Tests the set filter.
   *
   * @covers ::setFilter
   */
  public function testSetFilter() {
    $element = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'element',
        ],
      ],
    ];
    $value = [
      'element' => [
        'attributes' => [
          'class' => ['new-value'],
          'placeholder' => 'Label',
        ],
      ],
    ];
    $expected = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['new-value', 'old-value-2'],
          'id' => 'element',
          'placeholder' => 'Label',
        ],
      ],
    ];
    try {
      $result = TwigExtension::setFilter($element, $value);
      $this->assertEquals($expected, $result);
      $this->assertEquals(array_replace_recursive($element, $value), $result);
    }
    catch (\Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
  }

  /**
   * Tests exceptions during add filter.
   *
   * @covers ::addFilter
   */
  public function testAddFilterException() {
    try {
      TwigExtension::addFilter('not-an-array', 'key', 'value');
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (\Exception $e) {
      $needle = 'The add filter only works with arrays or "Traversable", got "string" as first argument.';
      if (method_exists($this, 'assertStringContainsString')) {
        $this->assertStringContainsString($needle, $e->getMessage());
      }
      else {
        $this->assertContains($needle, $e->getMessage());
      }
    }
  }

  /**
   * Tests the add filter.
   *
   * @param string $path
   *   The dotted-path to the deeply nested element to replace.
   * @param mixed $value
   *   The value to set.
   * @param array $expected
   *   The expected render array.
   *
   * @covers ::addFilter
   *
   * @dataProvider providerAddFilter
   */
  public function testAddFilter(string $path, $value, array $expected) {
    $element = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'element',
        ],
      ],
    ];

    $result = NULL;
    try {
      $result = TwigExtension::addFilter($element, $path, $value);
    }
    catch (\Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed to replace a value.');
  }

  /**
   * Data provider for testAddFilter().
   *
   * @see testAddFilter()
   */
  public function providerAddFilter(): array {
    return [
      'replacing a value' => [
        'path' => 'element.attributes.id',
        'value' => 'new-value',
        'expected' => [
          'existing' => 'value',
          'element' => [
            'type' => 'element',
            'attributes' => [
              'class' => ['old-value-1', 'old-value-2'],
              'id' => 'new-value',
            ],
          ],
        ],
      ],
      'setting a new property on an existing array' => [
        'path' => 'element.attributes.placeholder',
        'value' => 'new-value',
        'expected' => [
          'existing' => 'value',
          'element' => [
            'type' => 'element',
            'attributes' => [
              'class' => ['old-value-1', 'old-value-2'],
              'id' => 'element',
              'placeholder' => 'new-value',
            ],
          ],
        ],
      ],
      'targeting an existing array with a string' => [
        'path' => 'element.attributes.class',
        'value' => 'new-value',
        'expected' => [
          'existing' => 'value',
          'element' => [
            'type' => 'element',
            'attributes' => [
              'class' => ['old-value-1', 'old-value-2', 'new-value'],
              'id' => 'element',
            ],
          ],
        ],
      ],
      'targeting an existing array with an array' => [
        'path' => 'element.attributes.class',
        'value' => ['new-value-1', 'new-value-2'],
        'expected' => [
          'existing' => 'value',
          'element' => [
            'type' => 'element',
            'attributes' => [
              'class' => [
                'old-value-1',
                'old-value-2',
                'new-value-1',
                'new-value-2',
              ],
              'id' => 'element',
            ],
          ],
        ],
      ],
      'targeting a non-existent parent property' => [
        'path' => 'new-element.attributes.class',
        'value' => ['new-value'],
        'expected' => [
          'existing' => 'value',
          'element' => [
            'type' => 'element',
            'attributes' => [
              'class' => ['old-value-1', 'old-value-2'],
              'id' => 'element',
            ],
          ],
          'new-element' => ['attributes' => ['class' => ['new-value']]],
        ],
      ],
    ];
  }

}
