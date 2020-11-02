<?php

namespace Drupal\Tests\components\Unit\Template;

use Drupal\components\Template\TwigExtension;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigExtension as CoreTwigExtension;
use Drupal\Tests\UnitTestCase;
use Exception;
use Twig\Environment;

/**
 * @coversDefaultClass \Drupal\components\Template\TwigExtension
 * @group components
 */
class TwigExtensionTest extends UnitTestCase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $renderer;

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $urlGenerator;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $themeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $dateFormatter;

  /**
   * The system under test.
   *
   * @var \Drupal\components\Template\TwigExtension
   */
  protected $systemUnderTest;

  /**
   * The core TwigExtension.
   *
   * @var \Drupal\Core\Template\TwigExtension
   */
  protected $coreTwigExtension;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->renderer = $this->createMock('\Drupal\Core\Render\RendererInterface');
    $this->urlGenerator = $this->createMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $this->dateFormatter = $this->createMock('\Drupal\Core\Datetime\DateFormatterInterface');

    $this->systemUnderTest = new TwigExtension();
    $this->coreTwigExtension = new CoreTwigExtension($this->renderer, $this->urlGenerator, $this->themeManager, $this->dateFormatter);
  }

  /**
   * Tests creating #theme render arrays within a Twig template.
   *
   * @covers ::template
   */
  public function testTemplate() {
    $link = [
      '#type' => 'link',
      '#title' => 'example link',
      '#url' => 'https://example.com',
    ];
    $expected_render_array = [
      '#theme' => 'item_list',
      '#items' => [$link],
      '#printed' => FALSE,
    ];
    $expected = '<ul><li><a href="https://example.com">example link</a></li></ul>';

    $this->renderer->expects($this->exactly(2))
      ->method('render')
      ->with($expected_render_array)
      ->willReturn($expected);

    $loader = new StringLoader();
    $twig = new Environment($loader);
    $twig->setExtensions([$this->coreTwigExtension, $this->systemUnderTest]);

    try {
      $result = $twig->render(
        '{{ template("item-list.html.twig", items = [ link ] ) }}',
        [
          'link' => $link,
        ]
      );
      $this->assertEquals($expected, $result, "Works with template name");
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }

    try {
      $result = $twig->render(
        '{{ template("item_list", items = [ link ] ) }}',
        [
          'link' => $link,
        ]
      );
      $this->assertEquals($expected, $result, "Works with theme hook");
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }

    try {
      $twig->render(
        '{{ template("@stable/item-list.html.twig", items = [ link ] ) }}',
        [
          'link' => $link,
        ]
      );
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (Exception $e) {
      $this->assertContains('Templates with namespaces are not yet supported; "@stable/item-list.html.twig" given.', $e->getMessage());
    }
  }

  /**
   * Tests template function when using an array of theme hooks.
   *
   * @covers ::template
   */
  public function testTemplateWithThemeHookArray() {
    $link = [
      '#type' => 'link',
      '#title' => 'example link',
      '#url' => 'https://example.com',
    ];
    $expected_render_array = [
      '#theme' => ['item_list__dogs', 'item_list__cats'],
      '#items' => [$link],
      '#printed' => FALSE,
    ];
    $expected = '<ul><li><a href="https://example.com">example link</a></li></ul>';

    $this->renderer->expects($this->exactly(1))
      ->method('render')
      ->with($expected_render_array)
      ->willReturn($expected);

    $loader = new StringLoader();
    $twig = new Environment($loader);
    $twig->setExtensions([$this->coreTwigExtension, $this->systemUnderTest]);

    try {
      $result = $twig->render(
        '{{ template([ "item_list__dogs", "item_list__cats" ], items = [ link ] ) }}',
        [
          'link' => $link,
        ]
      );
      $this->assertEquals($expected, $result, "Works with an array of theme hooks");
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
  }

  /**
   * Tests the set filter.
   *
   * @covers ::setFilter
   */
  public function testSetFilter() {
    try {
      TwigExtension::setFilter('not-an-array', ['key' => 'value']);
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (Exception $e) {
      $this->assertContains('The set filter only works with arrays or "Traversable", got "string" as first argument.', $e->getMessage());
    }

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
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
  }

  /**
   * Tests the add filter.
   *
   * @covers ::addFilter
   */
  public function testAddFilter() {
    try {
      TwigExtension::addFilter('not-an-array', 'key', 'value');
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (Exception $e) {
      $this->assertContains('The add filter only works with arrays or "Traversable", got "string" as first argument.', $e->getMessage());
    }

    $data = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'element',
        ],
      ],
    ];

    // Test replacing a value.
    $element = $data;
    $result = NULL;
    $expected = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'new-value',
        ],
      ],
    ];
    try {
      $result = TwigExtension::addFilter($element, 'element.attributes.id', 'new-value');
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed to replace a value.');

    // Test setting a new property on an existing array.
    $element = $data;
    $result = NULL;
    $expected = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'element',
          'placeholder' => 'new-value',
        ],
      ],
    ];
    try {
      $result = TwigExtension::addFilter($element, 'element.attributes.placeholder', 'new-value');
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed setting a new property value.');

    // Test targeting an existing array with a string.
    $element = $data;
    $result = NULL;
    $expected = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2', 'new-value'],
          'id' => 'element',
        ],
      ],
    ];
    try {
      $result = TwigExtension::addFilter(
        $element,
        'element.attributes.class',
        'new-value'
      );
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed adding into a targeted array.');

    // Test targeting an existing array with an array.
    $element = $data;
    $result = NULL;
    $expected = [
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
    ];
    try {
      $result = TwigExtension::addFilter(
        $element,
        'element.attributes.class',
        ['new-value-1', 'new-value-2']
      );
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed merging a targeted array.');

    // Test targeting a non-existent parent property.
    $element = $data;
    $result = NULL;
    $expected = [
      'existing' => 'value',
      'element' => [
        'type' => 'element',
        'attributes' => [
          'class' => ['old-value-1', 'old-value-2'],
          'id' => 'element',
        ],
      ],
      'new-element' => ['attributes' => ['class' => ['new-value']]],
    ];
    try {
      $result = TwigExtension::addFilter(
        $element,
        'new-element.attributes.class',
        ['new-value']
      );
    }
    catch (Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
    $this->assertEquals($expected, $result, 'Failed adding new branch to an element.');
  }

}
