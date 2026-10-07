<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service\HtmlToMarkdown;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter;
use Drupal\markdownify\MarkdownifyHtmlConverterInterface;

// The markdownify module is optional. Declare a stand-in so tests can run
// without it being installed.
if (!interface_exists('Drupal\markdownify\MarkdownifyHtmlConverterInterface')) {
  // phpcs:ignore
  eval('namespace Drupal\\markdownify; interface MarkdownifyHtmlConverterInterface { public function convert(string $html): string; }');
}

/**
 * @coversDefaultClass \Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter
 * @group ai
 */
class MarkdownifyHtmlToMarkdownAdapterTest extends UnitTestCase {

  /**
   * Builds a config factory returning a given strip_whitespace value.
   */
  protected function createConfigFactory(?bool $stripWhitespace): ConfigFactoryInterface {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn(
      new class($stripWhitespace) {

        public function __construct(protected ?bool $stripWhitespace) {}

        /**
         * Spoof of Config::get().
         */
        public function get($name) {
          return $name === 'strip_whitespace' ? $this->stripWhitespace : NULL;
        }

      }
    );
    return $configFactory;
  }

  /**
   * @covers ::convert
   */
  public function testConvertDelegatesToMarkdownifyConverter(): void {
    $markdownifyConverter = $this->createMock(MarkdownifyHtmlConverterInterface::class);
    $markdownifyConverter->expects($this->once())
      ->method('convert')
      ->with('<p>Hello</p>')
      ->willReturn('Hello');

    $adapter = new MarkdownifyHtmlToMarkdownAdapter($markdownifyConverter, $this->createConfigFactory(TRUE));

    $this->assertSame('Hello', $adapter->convert('<p>Hello</p>'));
  }

  /**
   * @covers ::convert
   */
  public function testConvertPassesThroughEmptyString(): void {
    $markdownifyConverter = $this->createMock(MarkdownifyHtmlConverterInterface::class);
    $markdownifyConverter->expects($this->once())
      ->method('convert')
      ->with('')
      ->willReturn('');

    $adapter = new MarkdownifyHtmlToMarkdownAdapter($markdownifyConverter, $this->createConfigFactory(TRUE));

    $this->assertSame('', $adapter->convert(''));
  }

  /**
   * @covers ::convert
   */
  public function testStripWhitespaceIsAppliedToDelegatedOutput(): void {
    $markdownifyConverter = $this->createMock(MarkdownifyHtmlConverterInterface::class);
    $markdownifyConverter->method('convert')->willReturn("Title  \n\n\n\nNext  ");

    $adapter = new MarkdownifyHtmlToMarkdownAdapter($markdownifyConverter, $this->createConfigFactory(TRUE));

    // Trailing whitespace trimmed and 3+ blank lines collapsed to one.
    $this->assertSame("Title\n\nNext", $adapter->convert('<p>ignored</p>'));
  }

  /**
   * @covers ::convert
   */
  public function testStripWhitespaceDisabledLeavesDelegatedOutputUntouched(): void {
    $markdownifyConverter = $this->createMock(MarkdownifyHtmlConverterInterface::class);
    $markdownifyConverter->method('convert')->willReturn("Title  \n\n\n\nNext  ");

    $adapter = new MarkdownifyHtmlToMarkdownAdapter($markdownifyConverter, $this->createConfigFactory(FALSE));

    $this->assertSame("Title  \n\n\n\nNext  ", $adapter->convert('<p>ignored</p>'));
  }

}
