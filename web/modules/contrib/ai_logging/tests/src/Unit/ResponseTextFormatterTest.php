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
   * Tests that recorded tool calls are unwrapped and labeled "Tool calls".
   *
   * @covers ::format
   */
  public function testToolCalls(): void {
    $formatted = $this->formatter()->format('{"tool_calls":[{"name":"search_publications","arguments":{"query":"Myanmar coup"}}]}');
    $this->assertTrue($formatted['structured']);
    $this->assertSame('Tool calls', (string) $formatted['label']);
    // The 'tool_calls' wrapper is for storage; the reader sees the calls.
    $this->assertStringStartsWith('[', $formatted['text']);
    $this->assertStringContainsString('"query": "Myanmar coup"', $formatted['text']);
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
   * Tests the one-line summary used in a thread's overview.
   *
   * @covers ::summarize
   */
  public function testSummarize(): void {
    $formatter = $this->formatter();
    $tool_calls = '{"tool_calls":[{"name":"search","arguments":{}},{"name":"read","arguments":{}},{"name":"read","arguments":{}}]}';
    $this->assertSame('Tool calls: search, read ×2', $formatter->summarize($tool_calls));
    // A reply is shown on one line and cut to length.
    $this->assertSame('A short reply on two lines', $formatter->summarize("A short reply\non two lines"));
    $this->assertSame('abcde…', $formatter->summarize('abcdefghij', 5));
  }

  /**
   * Tests reading tool calls and summarizing their arguments.
   *
   * @covers ::getToolCalls
   * @covers ::summarizeArguments
   */
  public function testGetToolCalls(): void {
    $formatter = $this->formatter();
    $calls = $formatter->getToolCalls('{"tool_calls":[{"name":"search","arguments":{"query":"Sahel","limit":5}},{"name":"read"}]}');
    $this->assertSame([
      ['name' => 'search', 'arguments' => ['query' => 'Sahel', 'limit' => 5]],
      ['name' => 'read', 'arguments' => []],
    ], $calls);
    $this->assertNull($formatter->getToolCalls('{"result":true}'));
    $this->assertNull($formatter->getToolCalls('A reply.'));

    $this->assertSame('query: "Sahel", limit: 5', $formatter->summarizeArguments($calls[0]['arguments']));
    $this->assertSame('', $formatter->summarizeArguments([]));
    $this->assertSame('query: "abc…', $formatter->summarizeArguments(['query' => 'abcdefghij'], 11));
  }

  /**
   * Tests detecting and rendering Markdown.
   *
   * @covers ::isMarkdown
   * @covers ::renderMarkdown
   */
  public function testMarkdown(): void {
    $formatter = $this->formatter();
    $markdown = [
      "## Heading",
      "Intro:\n- one\n- two",
      "1. First",
      "Some **bold** text",
      "See [this](https://example.com).",
      "> Quoted",
      "| a | b |",
    ];
    foreach ($markdown as $text) {
      $this->assertTrue($formatter->isMarkdown($text), $text);
    }
    foreach (["A plain answer.\nOn two lines.", "Costs rose 5% - a #1 concern.", "x * y = z"] as $plain) {
      $this->assertFalse($formatter->isMarkdown($plain), $plain);
    }

    $html = (string) $formatter->renderMarkdown("## Title\n\n**Bold** <b>raw</b> [bad](javascript:alert(1)) https://example.com");
    $this->assertStringContainsString('<h2>Title</h2>', $html);
    $this->assertStringContainsString('<strong>Bold</strong>', $html);
    $this->assertStringContainsString('&lt;b&gt;raw&lt;/b&gt;', $html);
    $this->assertStringNotContainsString('javascript:', $html);
    $this->assertStringContainsString('<a rel="nofollow noopener noreferrer" href="https://example.com">', $html);

    // An image would load as soon as the page is viewed, sending whatever
    // the model put in its URL, so it is dropped.
    $html = (string) $formatter->renderMarkdown('Before ![x](https://example.com/?leak=secret) after <img src="https://example.com/b.png">');
    $this->assertStringNotContainsString('<img', $html);
    $this->assertStringNotContainsString('leak=secret', $html);
    $this->assertStringContainsString('Before', $html);
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
