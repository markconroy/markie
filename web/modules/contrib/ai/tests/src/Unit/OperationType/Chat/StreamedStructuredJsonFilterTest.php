<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai_test\Mock\MockIterator;
use Drupal\ai_test\Mock\MockStreamedChatIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that a streamed structured output response is filtered in JSON mode.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator
 */
class StreamedStructuredJsonFilterTest extends UnitTestCase {

  /**
   * The hostname filter the stream uses.
   *
   * @var \Drupal\ai\Service\HostnameFilter
   */
  protected HostnameFilter $filter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    $this->filter = new HostnameFilter($logger_factory, $this->createMock(ConfigFactoryInterface::class));
    $this->filter->setAllowedDomains(['example.com']);
    $container = new ContainerBuilder();
    $container->set('ai.hostname_filter_service', $this->filter);
    \Drupal::setContainer($container);
  }

  /**
   * Streams chunks of a structured output response through the filter.
   *
   * @param string[] $chunks
   *   The chunks the provider sends.
   * @param \Drupal\ai\Dto\HostnameFilterDto|null $dto
   *   (optional) The hostname filter settings of the caller.
   *
   * @return string
   *   The filtered text the consumer receives.
   */
  protected function streamStructured(array $chunks, ?HostnameFilterDto $dto = NULL): string {
    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $input->setChatStructuredJsonSchema(['name' => 'item', 'schema' => ['type' => 'object']]);
    if ($dto !== NULL) {
      $input->setHostnameFilter($dto);
    }
    $message = new MockStreamedChatIterator(new MockIterator($chunks));
    $message->setInput($input);
    $message->setEventDispatcher($this->createMock(EventDispatcherInterface::class));

    $output = '';
    foreach ($message as $part) {
      $output .= $part->getText();
    }
    return $output;
  }

  /**
   * Tests partial JSON chunks are filtered without the HTML round trip.
   */
  public function testStructuredStreamIsFilteredInJsonMode(): void {
    $output = $this->streamStructured([
      "{\"title\":\"A & B\",\n",
      '"body":"<a href=\\"https://example.com/ok\\">ok</a> <a href=\\"https://evil.com/x\\">bad</a>",' . "\n",
      '"img":"<img src=x onerror=alert(1)>"}',
    ]);

    $this->assertStringStartsWith('{"title":"A & B",' . "\n", $output);
    $this->assertStringContainsString('<a href=\\"https://example.com/ok\\">ok</a>', $output);
    $this->assertStringNotContainsString('&amp;', $output);
    $this->assertStringNotContainsString('evil.com', $output);
    $this->assertStringNotContainsString('onerror', $output);
    $this->assertNull($this->filter->snapshotSettings()['jsonMode']);
  }

  /**
   * Tests the stream still decodes after an untrusted URL was removed.
   */
  public function testStructuredStreamStaysDecodable(): void {
    $output = $this->streamStructured([
      "{\"title\":\"A & B\",\n",
      '"body":"<a href=\\"https://example.com/ok\\">ok</a> <a href=\\"https://evil.com/x\\">bad</a>"}',
    ]);

    $this->assertSame([
      'title' => 'A & B',
      'body' => '<a href="https://example.com/ok">ok</a> <a href="">bad</a>',
    ], json_decode($output, TRUE));
  }

  /**
   * Tests plain text mode stays on when the caller asked for it.
   */
  public function testCallerPlainTextModeIsKept(): void {
    // Only plain text mode removes a URL that is not part of a link.
    $output = $this->streamStructured(['{"u":"https://evil.com/x"}'], new HostnameFilterDto(plainTextMode: TRUE));
    $this->assertSame('{"u":""}', $output);
  }

}
