<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\ChatInput
 */
class ChatInputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather today?', $input->getMessages()[0]->getText());
    $input->setMessages([new ChatMessage('user', 'What is the weather tomorrow?')]);
    $this->assertEquals('user', $input->getMessages()[0]->getRole());
    $this->assertEquals('What is the weather tomorrow?', $input->getMessages()[0]->getText());
  }

  /**
   * Request metadata bag: empty by default, read/write by key, replace all.
   */
  public function testRequestMetadataBag(): void {
    $input = $this->getInput();
    $this->assertSame([], $input->getAllRequestMetadata());
    $this->assertNull($input->getRequestMetadataValue('missing'));

    $input->setRequestMetadataValue('entity_context', [
      'entity_type' => 'node',
      'bundle' => 'blog_post',
      'id' => '42',
    ]);
    $stored = $input->getRequestMetadataValue('entity_context');
    $this->assertIsArray($stored);
    $this->assertSame('node', $stored['entity_type']);
    $this->assertSame('blog_post', $stored['bundle']);
    $this->assertSame('42', $stored['id']);
    $this->assertArrayHasKey('entity_context', $input->getAllRequestMetadata());

    $input->setAllRequestMetadata(['other_key' => 'other_value']);
    $this->assertNull($input->getRequestMetadataValue('entity_context'));
    $this->assertSame('other_value', $input->getRequestMetadataValue('other_key'));
  }

  /**
   * Helper function to get the events.
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\ai\OperationType\Chat\ChatInput
   *   The input.
   */
  public function getInput(): ChatInput {
    $messages[] = new ChatMessage('user', 'What is the weather today?');
    return new ChatInput($messages);
  }

}
