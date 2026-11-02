<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\Service\HostnameFilter;
use Drupal\ai_test\Mock\MockIterator;
use Drupal\ai_test\Mock\MockStreamedChatIterator;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that streamed Markdown link destinations are flushed in one piece.
 *
 * A streamed response is flushed in fragments (on a newline, or when
 * maxBufferSize is reached) and each fragment is filtered on its own. The
 * consumer joins the fragments again, so a destination split across two
 * fragments is rendered whole although no single filter pass saw it.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator
 */
class StreamedMarkdownLinkFlushTest extends UnitTestCase {

  /**
   * The fragments handed to the hostname filter, in order.
   *
   * @var string[]
   */
  protected array $filtered = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A pass-through filter double that records every fragment it is given.
    $hostname_filter = $this->createMock(HostnameFilter::class);
    $hostname_filter->method('filterText')
      ->willReturnCallback(function (string $text): string {
        $this->filtered[] = $text;
        return $text;
      });

    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $hostname_filter);
    \Drupal::setContainer($container);
  }

  /**
   * Tests a link destination always reaches the filter in a single fragment.
   *
   * @param string $markdown
   *   The complete Markdown the model produces.
   * @param string $destination
   *   The link destination that must never be split across fragments.
   *
   * @dataProvider providerMarkdownLinks
   */
  public function testLinkDestinationIsNotSplitAcrossFlushes(string $markdown, string $destination): void {
    foreach ([1, 3, 7, 10, 23, 57, 100] as $size) {
      $this->filtered = [];
      $output = $this->streamChunks(str_split($markdown, $size));

      $this->assertSame($markdown, $output, sprintf('Round-trip mismatch in %d-character chunks.', $size));
      $whole = array_filter($this->filtered, static fn(string $fragment): bool => str_contains($fragment, $destination));
      $this->assertNotEmpty($whole, sprintf('Destination split across flushes in %d-character chunks.', $size));
    }
  }

  /**
   * Markdown links in positions where a flush used to cut the destination.
   *
   * @return array<string, array{0: string, 1: string}>
   *   Sets of [markdown, destination].
   */
  public static function providerMarkdownLinks(): array {
    return [
      'newline in link text' => [
        "Intro\n[first\nsecond](https://example.org/a/b/c)\nEnd\n",
        '(https://example.org/a/b/c)',
      ],
      'link after a newline' => [
        "Intro\n[text](mailto:someone@example.org)\nEnd\n",
        '(mailto:someone@example.org)',
      ],
      'link past the buffer size' => [
        str_repeat('word ', 35) . "[text](mailto:someone@example.org) end\n",
        '(mailto:someone@example.org)',
      ],
      'space before destination' => [
        "Intro\n[text] (mailto:someone@example.org)\nEnd\n",
        '(mailto:someone@example.org)',
      ],
      'reference definition' => [
        "Intro\n\n[ref]: mailto:someone@example.org\n\n[text][ref]\n",
        ': mailto:someone@example.org',
      ],
    ];
  }

  /**
   * Streams chunks through the iterator and returns the joined output.
   *
   * @param string[] $chunks
   *   The chunks the provider emits.
   *
   * @return string
   *   The concatenated streamed text.
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

}
