<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageInterface;
use Drupal\Tests\ai\Mock\MockIterator;
use Drupal\Tests\ai\Mock\MockStreamedChatIterator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that the streamed chat message works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\StreamedChatMessageIterator
 */
class StreamedChatMessageIteratorTest extends TestCase {

  /**
   * A MockStreamedChatIterator instance.
   *
   * @var \Drupal\Tests\ai\Mock\MockStreamedChatIterator
   */
  private MockStreamedChatIterator $streamedChatMessage;

  /**
   * The output of the message.
   *
   * @var array
   */
  private array $output = [
    'Testing ',
    'to ',
    'stream ',
    'out ',
    'the ',
    'message.',
  ];

  /**
   * Set up the test.
   */
  protected function setUp(): void {
    $mock_event_dispatcher = $this->createMock(EventDispatcherInterface::class);
    $mock_event_dispatcher
      ->method('dispatch');

    $iterator = new MockIterator($this->output);
    $message = new MockStreamedChatIterator($iterator);
    // Attach the event dispatcher.
    $message->setEventDispatcher($mock_event_dispatcher);
    $this->streamedChatMessage = $message;
  }

  /**
   * Test that you can stream out the message.
   */
  public function testStreamedMessage() {
    $message = $this->streamedChatMessage;
    foreach ($message as $key => $part) {
      $this->assertEquals($this->output[$key], $part->getText());
    }
  }

  /**
   * Test that you can collect an output/message after streaming.
   */
  public function testCollectMessage() {
    $message = $this->streamedChatMessage;
    // FIrst check so the output is empty, before consuming the iterator.
    $output = $message->reconstructChatOutput();
    // Check so its a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $output);
    // Check so you can get a ChatMessage from the output.
    $chat_message = $output->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $chat_message);
    // Check so the role of the message is empty.
    $this->assertEquals('', $chat_message->getRole());
    // Check so the text is empty.
    $this->assertEquals('', $chat_message->getText());
    // Now consume the iterator.
    foreach ($message as $part) {
      // Check so the text is not empty.
      $this->assertNotEmpty($part->getText());
      // Check so the part is a StreamedChatMessageInterface.
      $this->assertInstanceOf(StreamedChatMessageInterface::class, $part);
    }
    // Now check the output again.
    $output = $message->reconstructChatOutput();
    // Check so its a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $output);
    // Check so you can get a ChatMessage from the output.
    $chat_message = $output->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $chat_message);
    // Check so the role of the message is assistant.
    $this->assertEquals('assistant', $chat_message->getRole());
    // Check so the text is the same as the output.
    $this->assertEquals(implode('', $this->output), $chat_message->getText());
    // Check so all tokens are set correctly.
    $tokenUsage = $output->getTokenUsage();
    $this->assertEquals(0, $tokenUsage->total);
    $this->assertEquals(0, $tokenUsage->input);
    $this->assertEquals(0, $tokenUsage->output);
    $this->assertEquals(0, $tokenUsage->cached);
    $this->assertEquals(0, $tokenUsage->reasoning);
  }

  /**
   * Test to set tokens.
   */
  public function testSetTokens() {
    $message = $this->streamedChatMessage;
    $messages = $message->getStreamChatMessages();
    // Add a token chunk to the message.
    $chunk = new StreamedChatMessage('', '');
    $chunk->setTotalTokenUsage(100);
    $chunk->setInputTokenUsage(80);
    $chunk->setOutputTokenUsage(20);
    $chunk->setCachedTokenUsage(10);
    $chunk->setReasoningTokenUsage(5);
    $messages[] = $chunk;
    $message->setStreamChatMessages($messages);
    // Now iterate over the messages and check the tokens.
    foreach ($message as $key => $part) {
      // All chunks outside of the last one should have text.
      if ($key < count($this->output) - 1) {
        $this->assertNotEmpty($part->getText());
      }
    }
    // Now check the output again.
    $output = $message->reconstructChatOutput();
    // Check so its a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $output);
    // Check so all tokens are set correctly.
    $tokenUsage = $output->getTokenUsage();
    $this->assertEquals(100, $tokenUsage->total);
    $this->assertEquals(80, $tokenUsage->input);
    $this->assertEquals(20, $tokenUsage->output);
    $this->assertEquals(10, $tokenUsage->cached);
    $this->assertEquals(5, $tokenUsage->reasoning);
  }

}
