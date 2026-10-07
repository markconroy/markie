<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service;

use Drupal\ai\Service\CommonMarkConverterFactory;
use Drupal\Tests\UnitTestCase;
use League\CommonMark\CommonMarkConverter;

/**
 * Tests the CommonMark converter factory.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Service\CommonMarkConverterFactory
 */
class CommonMarkConverterFactoryTest extends UnitTestCase {

  /**
   * The factory under test.
   *
   * @var \Drupal\ai\Service\CommonMarkConverterFactory
   */
  protected CommonMarkConverterFactory $factory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->factory = new CommonMarkConverterFactory();
  }

  /**
   * The factory returns a CommonMark converter.
   *
   * @covers ::fromOptions
   */
  public function testFromOptionsReturnsConverter(): void {
    $this->assertInstanceOf(CommonMarkConverter::class, $this->factory->fromOptions());
  }

  /**
   * A converter created with no options renders markdown as HTML.
   *
   * @covers ::fromOptions
   */
  public function testFromOptionsWithDefaultOptions(): void {
    $html = trim($this->factory->fromOptions()->convert('**bold**')->getContent());
    $this->assertSame('<p><strong>bold</strong></p>', $html);
  }

  /**
   * Default options strip raw HTML instead of passing it through.
   *
   * @covers ::fromOptions
   */
  public function testFromOptionsStripsHtmlByDefault(): void {
    $html = $this->factory->fromOptions()->convert('<b>raw</b>')->getContent();
    $this->assertStringNotContainsString('<b>', $html);
    $this->assertStringContainsString('raw', $html);
  }

  /**
   * Default options strip unsafe javascript: links.
   *
   * @covers ::fromOptions
   */
  public function testFromOptionsDisallowsUnsafeLinksByDefault(): void {
    $html = $this->factory->fromOptions()->convert('[x](javascript:alert(1))')->getContent();
    $this->assertStringNotContainsString('javascript:', $html);
  }

  /**
   * Caller options override defaults without dropping unspecified defaults.
   *
   * With html_input escape, raw HTML is escaped. Unsafe links stay disallowed
   * unless the caller also overrides allow_unsafe_links.
   *
   * @covers ::fromOptions
   */
  public function testFromOptionsMergesCallerOverrides(): void {
    $converter = $this->factory->fromOptions(['html_input' => 'escape']);
    $html = $converter->convert('<b>raw</b>')->getContent();
    $this->assertStringContainsString('&lt;b&gt;raw&lt;/b&gt;', $html);

    $html = $converter->convert('[x](javascript:alert(1))')->getContent();
    $this->assertStringNotContainsString('javascript:', $html);
  }

}
