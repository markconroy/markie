<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_logging\Unit;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_logging\ResponseTextFormatter;

/**
 * Tests formatting the reply text of an AI log.
 *
 * @group ai_logging
 * @coversDefaultClass \Drupal\ai_logging\ResponseTextFormatter
 */
class ResponseTextFormatterTest extends UnitTestCase {

  /**
   * Tests that a JSON answer is pretty-printed and labeled "Result".
   *
   * @covers ::format
   */
  public function testStructuredResult(): void {
    $formatted = $this->formatter()->format('{"safe":true,"url":"https://example.com/a"}');
    $this->assertTrue($formatted['structured']);
    $this->assertSame('Result', (string) $formatted['label']);
    $this->assertSame("{\n    \"safe\": true,\n    \"url\": \"https://example.com/a\"\n}", $formatted['text']);
  }

  /**
   * Tests that other text is shown as it is and labeled "Reply".
   *
   * @covers ::format
   */
  public function testPlainReply(): void {
    // Valid JSON that is not an object or array is still a plain reply.
    foreach (['Hello world', '{not json', '42', '"quoted"'] as $text) {
      $formatted = $this->formatter()->format($text);
      $this->assertFalse($formatted['structured'], $text);
      $this->assertSame('Reply', (string) $formatted['label']);
      $this->assertSame($text, $formatted['text']);
    }
  }

  /**
   * Tests that a transcript's role and tool-call lines are emphasized.
   *
   * @covers ::buildTranscript
   */
  public function testTranscriptEmphasizesStructure(): void {
    $transcript = implode("\n", [
      'user',
      'What is going on in Hormuz?',
      'assistant',
      '[calls search_publications] {"query":"Hormuz"}',
      'tool (search_publications)',
      '[Result 1]',
    ]);
    $built = $this->formatter()->buildTranscript($this->label(), $transcript);
    $rendered = (string) $built['#context']['text'];

    // The lines that mark where one message ends and the next begins,
    // capitalized for reading.
    $this->assertStringContainsString('<strong>User</strong>', $rendered);
    $this->assertStringContainsString('<strong>Assistant</strong>', $rendered);
    $this->assertStringContainsString('<strong>Tool (search_publications)</strong>', $rendered);
    // The tool call is emphasized, but not the arguments after it - which
    // stay escaped, quotes and all.
    $this->assertStringContainsString('<strong>[calls search_publications]</strong> {&quot;query&quot;:&quot;Hormuz&quot;}', $rendered);
    // Everything else is left alone.
    $this->assertStringContainsString("\nWhat is going on in Hormuz?\n", $rendered);
    $this->assertStringContainsString("\n[Result 1]", $rendered);
  }

  /**
   * Tests that transcript content is escaped, never rendered as markup.
   *
   * @covers ::buildTranscript
   */
  public function testTranscriptEscapesContent(): void {
    $built = $this->formatter()->buildTranscript(
      $this->label(),
      "user\n<script>alert(1)</script>\ntool (<b>x</b>)",
    );
    $rendered = (string) $built['#context']['text'];

    $this->assertStringNotContainsString('<script>', $rendered);
    $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $rendered);
    // A role line is emphasized, but a tool name is still escaped inside it.
    $this->assertStringContainsString('<strong>Tool (&lt;b&gt;x&lt;/b&gt;)</strong>', $rendered);
  }

  /**
   * Creates a label for the built render arrays.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The label.
   */
  protected function label(): TranslatableMarkup {
    return new TranslatableMarkup('Prompt', [], [], $this->getStringTranslationStub());
  }

  /**
   * Creates the formatter under test.
   *
   * @return \Drupal\ai_logging\ResponseTextFormatter
   *   The formatter.
   */
  protected function formatter(): ResponseTextFormatter {
    $formatter = new ResponseTextFormatter();
    $formatter->setStringTranslation($this->getStringTranslationStub());
    return $formatter;
  }

}
