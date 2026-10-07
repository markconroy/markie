<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai_test\Mock\MockIterator;
use Drupal\ai_test\Mock\MockStreamedChatIterator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Masterminds\HTML5;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that streamed HTML output is not corrupted by mid-element flushing.
 *
 * Regression test for #3586558. A streamed chat response is buffered and
 * flushed in fragments (on a newline, or when maxBufferSize is reached). Each
 * flushed fragment is run through HostnameFilter::filterText(), which parses it
 * as an HTML fragment and re-serializes it. When the flush landed mid-element
 * (e.g. an open "<strong>"/"<p>" whose closing tag had not streamed yet), the
 * HTML5 parser auto-closed the open tags, injecting spurious "</strong>" /
 * "</p>" into translated content and splitting sentences across paragraphs.
 *
 * The scenarios mirror the real reproduction from the issue: an AI Translate /
 * chat response streams token-by-token, and an inline element (a <strong>
 * phrase, a link, a list item) runs longer than the 100-character flush buffer
 * so the flush lands mid-element. The filter is doubled with the exact
 * operation that corrupts a partial fragment (HTML5 load + save), so a test
 * fails if the iterator ever hands it unbalanced HTML and passes once the
 * buffer is held until the fragment is balanced.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator
 * @see https://www.drupal.org/project/ai/issues/3586558
 */
class StreamedHtmlIntegrityTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A HostnameFilter double that reproduces the real service's HTML mode: it
    // parses the fragment and re-serializes it. This is the exact operation
    // that corrupts a partial fragment, so a test fails if the iterator hands
    // it unbalanced HTML, and passes once the buffer is held until balanced.
    $html5 = new HTML5();
    $hostname_filter = $this->createMock(HostnameFilter::class);
    $hostname_filter->method('filterText')
      ->willReturnCallback(static fn(string $text): string => $html5->saveHTML($html5->loadHTMLFragment($text)));

    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);
  }

  /**
   * The exact scenario reported in the issue must stream without corruption.
   *
   * A page body containing an inline <strong> phrase that runs past the
   * 100-character flush buffer is translated through the GUI; the streamed
   * result previously gained spurious </strong> / </p> tags mid-sentence.
   */
  public function testReportedTranslationScenarioIsNotCorrupted(): void {
    // The chunks deliberately split HTML tags across stream parts. They are
    // long enough that the buffer crosses maxBufferSize (100) mid-element,
    // which previously forced a flush while <strong> / <p> were still open.
    // Newlines only appear at balanced (end-of-paragraph) boundaries.
    $chunks = [
      "<p>'The Secrets of the Court' is a series of scavenger hunts for classes: <strong>from the 1st ",
      "grade up to and including the 6th grade.</strong> The focus is on West Flanders and the ",
      "magnificent neo-gothic heritage building.</p>\n",
      '<p><strong>More information and the registration link can be ',
      "found below.</strong></p>\n",
    ];
    $expected = implode('', $chunks);

    $output = $this->streamChunks($chunks);

    // The streamed output must be identical to the original, well-formed HTML.
    $this->assertSame($expected, $output);

    // Guard against the specific corruption signature: only the two opening and
    // two closing paragraph tags from the input, and intact <strong> elements.
    $this->assertSame(2, substr_count($output, '<p>'));
    $this->assertSame(2, substr_count($output, '</p>'));
    $this->assertStringContainsString('<strong>from the 1st grade up to and including the 6th grade.</strong>', $output);
  }

  /**
   * Realistic HTML must survive token-level streaming at any chunk boundary.
   *
   * Each scenario is streamed several times, re-chunked at different sizes, to
   * emulate how a real provider emits tokens: the tag splits never align, so
   * the flush repeatedly lands mid-element. Whatever the chunking, the tag
   * multiset must be preserved (no injected closing tags) and the long inline
   * elements must stay intact.
   *
   * @param string $html
   *   The complete, well-formed HTML the model produces.
   * @param string[] $intact_phrases
   *   Substrings that must appear verbatim in the output (the inline elements
   *   that used to be split by a mid-element flush).
   *
   * @dataProvider providerRealisticHtml
   */
  public function testRealisticStreamedHtmlIntegrity(string $html, array $intact_phrases): void {
    // Chunk sizes chosen to land inside tags and to straddle the 100-character
    // flush buffer: 1 (extreme token-per-char), and a few odd sizes that never
    // align with the markup.
    foreach ([1, 7, 23, 57, 100] as $size) {
      $output = $this->streamChunks(str_split($html, $size));

      $this->assertSame(
        $html,
        $output,
        sprintf('Round-trip mismatch when streamed in %d-character chunks.', $size),
      );
      $this->assertTagMultisetPreserved($html, $output, $size);
      foreach ($intact_phrases as $phrase) {
        $this->assertStringContainsString(
          $phrase,
          $output,
          sprintf('Inline element was split when streamed in %d-character chunks.', $size),
        );
      }
    }
  }

  /**
   * Realistic HTML bodies drawn from the reported bug and lorem ipsum copy.
   *
   * @return array<string, array{0: string, 1: string[]}>
   *   Sets of [html, intact-phrases].
   */
  public static function providerRealisticHtml(): array {
    return [
      // A long emphasized phrase (well over 100 chars) inside a paragraph.
      'long inline strong' => [
        "<p>Lorem ipsum dolor sit amet, <strong>consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</strong> ut enim ad minim veniam.</p>\n",
        ['<strong>consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</strong>'],
      ],
      // A link whose href alone pushes the element past the flush buffer. This
      // also exercises the URL-safety buffer alongside the HTML balancing.
      'long anchor link' => [
        '<p>Registration details are available on the <a href="https://example.com/events/the-secrets-of-the-court/registration-and-more-information">official event page</a> for all classes.</p>' . "\n",
        ['<a href="https://example.com/events/the-secrets-of-the-court/registration-and-more-information">official event page</a>'],
      ],
      // A list where each item is long enough to flush mid-<li>.
      'list with long items' => [
        "<ul><li>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</li><li>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim.</li></ul>\n",
        [
          '<li>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</li>',
          '<li>Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim.</li>',
        ],
      ],
      // Multiple paragraphs, each with nested inline emphasis and a link.
      'multiple paragraphs' => [
        "<p>Sed ut <em>perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque</em> laudantium.</p>\n<p>Totam rem aperiam, eaque ipsa quae ab illo inventore veritatis et <strong>quasi architecto beatae vitae dicta sunt explicabo</strong>.</p>\n",
        [
          '<em>perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque</em>',
          '<strong>quasi architecto beatae vitae dicta sunt explicabo</strong>',
        ],
      ],
      // Void elements (<br>) interleaved with a long inline element: the void
      // tag must not be treated as an open element that stalls the flush.
      'void elements in flow' => [
        "<p>Nemo enim ipsam voluptatem quia voluptas sit aspernatur aut odit aut fugit.<br>Sed quia consequuntur magni dolores eos qui ratione <strong>voluptatem sequi nesciunt neque porro quisquam est qui dolorem</strong> ipsum.</p>\n",
        ['<strong>voluptatem sequi nesciunt neque porro quisquam est qui dolorem</strong>'],
      ],
    ];
  }

  /**
   * The hard ceiling must force a flush even when a tag never closes.
   *
   * A malformed model output can open a tag and never close it. shouldFlush()
   * normally holds the buffer until every tag is balanced, so this guards
   * against unbounded buffer growth: once the buffer passes
   * MAX_HTML_BUFFER_SIZE the HTML hold is abandoned and the buffer is flushed.
   * This safety valve is internal and cannot be observed through
   * balanced-content streaming, so it is asserted directly.
   */
  public function testBufferCeilingForcesFlush(): void {
    $iterator = new MockStreamedChatIterator(new MockIterator([]));
    // An open <p> that never closes, padded past MAX_HTML_BUFFER_SIZE (102400).
    // No newline, so only the ceiling can trigger the flush.
    $this->setBuffer($iterator, '<p>' . str_repeat('a', 102400));

    $this->assertTrue($this->invokeShouldFlush($iterator));
  }

  /**
   * HTML balancing is skipped once a streaming guardrail is registered.
   *
   * Guardrails do their own start/stop buffering on the post-filter chunks, so
   * holding the buffer across their markers would merge content across a
   * guardrail boundary and change what the guardrail evaluates. This decision
   * lives inside shouldFlush() and is asserted directly.
   */
  public function testHtmlHoldIsSkippedWhenGuardrailRegistered(): void {
    $iterator = new MockStreamedChatIterator(new MockIterator([]));
    $iterator->addStreamingGuardrail($this->createMock(StreamableGuardrailInterface::class));
    // Ends inside an open <strong> but has a newline: with a guardrail present
    // the HTML hold is bypassed and the newline triggers the flush.
    $this->setBuffer($iterator, "<p>text</p>\n<strong>bold");

    $this->assertTrue($this->invokeShouldFlush($iterator));
  }

  /**
   * Streams a set of chunks through a fresh iterator and returns the output.
   *
   * @param string[] $chunks
   *   The stream parts, in order.
   *
   * @return string
   *   The concatenated text yielded to the consumer.
   */
  private function streamChunks(array $chunks): string {
    $message = new MockStreamedChatIterator(new MockIterator($chunks));
    $message->setEventDispatcher($this->createMock(EventDispatcherInterface::class));

    $output = '';
    foreach ($message as $part) {
      $output .= $part->getText();
    }
    return $output;
  }

  /**
   * Asserts the output contains exactly the same HTML tags as the input.
   *
   * The bug injected extra closing tags, so comparing the full multiset of
   * opening and closing tag names (ignoring attributes) catches any injected
   * or dropped tag regardless of chunk boundaries.
   *
   * @param string $input
   *   The original HTML.
   * @param string $output
   *   The streamed HTML.
   * @param int $size
   *   The chunk size under test, for the failure message.
   */
  private function assertTagMultisetPreserved(string $input, string $output, int $size): void {
    $this->assertSame(
      $this->tagCounts($input),
      $this->tagCounts($output),
      sprintf('Tag multiset changed when streamed in %d-character chunks.', $size),
    );
  }

  /**
   * Counts each opening/closing HTML tag name in a string.
   *
   * @param string $html
   *   The HTML to scan.
   *
   * @return array<string, int>
   *   Map of normalized tag token (e.g. "p", "/p") to occurrence count.
   */
  private function tagCounts(string $html): array {
    preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b/', $html, $matches, PREG_SET_ORDER);
    $counts = [];
    foreach ($matches as $match) {
      $token = $match[1] . strtolower($match[2]);
      $counts[$token] = ($counts[$token] ?? 0) + 1;
    }
    ksort($counts);
    return $counts;
  }

  /**
   * Sets the protected buffer property on the iterator under test.
   *
   * @param \Drupal\ai_test\Mock\MockStreamedChatIterator $iterator
   *   The iterator to mutate.
   * @param string $buffer
   *   The buffer contents.
   */
  private function setBuffer(MockStreamedChatIterator $iterator, string $buffer): void {
    $property = new \ReflectionProperty($iterator, 'buffer');
    $property->setAccessible(TRUE);
    $property->setValue($iterator, $buffer);
  }

  /**
   * Invokes the private shouldFlush() method on the iterator under test.
   *
   * @param \Drupal\ai_test\Mock\MockStreamedChatIterator $iterator
   *   The iterator to inspect.
   *
   * @return bool
   *   The shouldFlush() return value.
   */
  private function invokeShouldFlush(MockStreamedChatIterator $iterator): bool {
    $method = new \ReflectionMethod($iterator, 'shouldFlush');
    $method->setAccessible(TRUE);
    return $method->invoke($iterator);
  }

}
