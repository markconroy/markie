<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service\HtmlToMarkdown;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter;

/**
 * @coversDefaultClass \Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter
 * @group ai
 */
class HtmlToMarkdownConverterTest extends UnitTestCase {

  /**
   * Builds a converter backed by an in-memory ai.html_to_markdown.settings.
   *
   * @param array $settings
   *   Config values keyed by setting name; missing keys resolve to NULL,
   *   letting league/html-to-markdown fall back to its own defaults.
   */
  protected function createConverter(array $settings): HtmlToMarkdownConverter {
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn(
      new class($settings) {

        public function __construct(protected array $settings) {}

        /**
         * Spoof of Config::get().
         */
        public function get($name) {
          return $this->settings[$name] ?? NULL;
        }

      }
    );
    return new HtmlToMarkdownConverter($configFactory);
  }

  /**
   * @covers ::convert
   */
  public function testEmptyHtmlReturnsEmptyString(): void {
    $converter = $this->createConverter([]);
    $this->assertSame('', $converter->convert(''));
  }

  /**
   * @covers ::convert
   */
  public function testHeaderStyleAtxIsConfigurable(): void {
    $converter = $this->createConverter(['header_style' => 'atx']);
    $this->assertSame('# Title', trim($converter->convert('<h1>Title</h1>')));
  }

  /**
   * @covers ::convert
   */
  public function testHeaderStyleSetextIsConfigurable(): void {
    $converter = $this->createConverter(['header_style' => 'setext']);
    $markdown = trim($converter->convert('<h1>Title</h1>'));
    $this->assertStringContainsString('Title', $markdown);
    $this->assertStringContainsString('====', $markdown);
  }

  /**
   * @covers ::convert
   */
  public function testStripTagsRemovesUnsupportedTags(): void {
    $converter = $this->createConverter(['strip_tags' => TRUE]);
    $markdown = $converter->convert('<span>Text</span>');
    $this->assertSame('Text', $markdown);
  }

  /**
   * @covers ::convert
   */
  public function testListItemStyleIsConfigurable(): void {
    $converter = $this->createConverter(['list_item_style' => '+']);
    $this->assertSame('+ One', trim($converter->convert('<ul><li>One</li></ul>')));
  }

  /**
   * @covers ::convert
   */
  public function testTablesAreConvertedToMarkdown(): void {
    // The TableConverter is not registered by league/html-to-markdown by
    // default, so it must be added explicitly for the table_pipe_escape and
    // table_caption_side settings to have any effect.
    $converter = $this->createConverter([]);
    $html = '<table><tr><th>A</th><th>B</th></tr><tr><td>1</td><td>2</td></tr></table>';
    $markdown = $converter->convert($html);
    $this->assertStringContainsString('| A | B |', $markdown);
    $this->assertStringContainsString('| 1 | 2 |', $markdown);
  }

  /**
   * @covers ::convert
   */
  public function testStripWhitespaceCollapsesBlankLinesByDefault(): void {
    // Setting `strip_whitespace` is not a league/html-to-markdown option; it's
    // an AI module post-processing step, defaulting to TRUE when unset.
    // Repeated <br> tags produce trailing-whitespace-only lines and 3+ blank
    // lines, which should be collapsed away.
    $converter = $this->createConverter([]);
    $markdown = $converter->convert('<h1>Title</h1><br><br><br><p>Next</p>');
    $this->assertSame("Title\n=====\n\nNext", $markdown);
  }

  /**
   * @covers ::convert
   */
  public function testStripWhitespaceDisabledKeepsRawOutput(): void {
    $converter = $this->createConverter(['strip_whitespace' => FALSE]);
    $markdown = $converter->convert('<h1>Title</h1><br><br><br><p>Next</p>');
    $this->assertSame("Title\n=====\n\n  \n  \n  \nNext", $markdown);
  }

}
