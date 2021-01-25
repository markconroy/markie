<?php

namespace Drupal\Tests\components\Unit\Template;

use Drupal\components\Template\TwigExtension;
use Drupal\Core\Template\Loader\StringLoader;
use Drupal\Core\Template\TwigExtension as CoreTwigExtension;
use Drupal\Tests\UnitTestCase;
use Twig\Environment;

/**
 * @coversDefaultClass \Drupal\components\Template\TwigExtension
 * @group components
 */
class TwigExtensionFunctionsTest extends UnitTestCase {

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
   * The Twig environment.
   *
   * @var \Twig\Environment
   */
  protected $twigEnvironment;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->renderer = $this->createMock('\Drupal\Core\Render\RendererInterface');
    $urlGenerator = $this->createMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $themeManager = $this->createMock('\Drupal\Core\Theme\ThemeManagerInterface');
    $dateFormatter = $this->createMock('\Drupal\Core\Datetime\DateFormatterInterface');

    $this->systemUnderTest = new TwigExtension();
    $coreTwigExtension = new CoreTwigExtension($this->renderer, $urlGenerator, $themeManager, $dateFormatter);

    $loader = new StringLoader();
    $this->twigEnvironment = new Environment($loader);
    $this->twigEnvironment->setExtensions([
      $coreTwigExtension,
      $this->systemUnderTest,
    ]);
  }

  /**
   * Tests incorrectly using a Twig namespaced template name.
   *
   * @covers ::template
   */
  public function testTemplateNamespaceException() {
    $this->renderer->expects($this->exactly(0))
      ->method('render');

    try {
      $this->twigEnvironment->render(
        '{{ template("@stable/item-list.html.twig", items = [ link ] ) }}',
        ['link' => '']
      );
      $this->fail('Expected Exception, none was thrown.');
    }
    catch (\Exception $e) {
      $needle = 'Templates with namespaces are not supported; "@stable/item-list.html.twig" given.';
      if (method_exists($this, 'assertStringContainsString')) {
        $this->assertStringContainsString($needle, $e->getMessage());
      }
      else {
        $this->assertContains($needle, $e->getMessage());
      }
    }
  }

  /**
   * Tests creating #theme render arrays within a Twig template.
   *
   * @param string $template
   *   The inline template to render.
   * @param string $message
   *   The error message to show if test fails.
   *
   * @covers ::template
   *
   * @dataProvider providerTemplate
   */
  public function testTemplate(string $template, string $message) {
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

    $this->renderer->expects($this->exactly(1))
      ->method('render')
      ->with($expected_render_array)
      ->willReturn($expected);

    try {
      $result = $this->twigEnvironment->render(
        $template,
        [
          'link' => $link,
        ]
      );
      $this->assertEquals($expected, $result, $message);
    }
    catch (\Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
  }

  /**
   * Data provider for testTemplate().
   *
   * @see testTemplate()
   */
  public function providerTemplate(): array {
    return [
      'Template name' => [
        'template' => '{{ template("item-list.html.twig", items = [ link ] ) }}',
        'message' => 'Works with template name',
      ],
      'Theme hook' => [
        'template' => '{{ template("item_list", items = [ link ] ) }}',
        'message' => 'Works with theme hook',
      ],
    ];
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

    try {
      $result = $this->twigEnvironment->render(
        '{{ template([ "item_list__dogs", "item_list__cats" ], items = [ link ] ) }}',
        [
          'link' => $link,
        ]
      );
      $this->assertEquals($expected, $result, 'Works with an array of theme hooks');
    }
    catch (\Exception $e) {
      $this->fail('No Exception expected; "' . $e->getMessage() . '" thrown.');
    }
  }

}
