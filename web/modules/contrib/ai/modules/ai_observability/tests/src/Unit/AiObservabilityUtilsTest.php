<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\ai_observability\AiObservabilityUtils
 *
 * @group ai_observability
 */
class AiObservabilityUtilsTest extends TestCase {

  /**
   * @covers ::aiInputToString
   */
  public function testAiInputToStringUsesStructuredPayload(): void {
    $input = new ChatInput([
      new ChatMessage('system', 'Hello'),
      new ChatMessage('user', 'World'),
    ]);

    $result = AiObservabilityUtils::aiInputToString($input);
    $decoded = json_decode($result, TRUE, flags: JSON_THROW_ON_ERROR);

    $this->assertSame('system', $decoded['messages'][0]['role']);
    $this->assertSame('World', $decoded['messages'][1]['text']);
  }

  /**
   * @covers ::aiInputToString
   */
  public function testAiInputToStringLegacyPath(): void {
    $input = new ChatInput([
      new ChatMessage('system', 'Hello'),
      new ChatMessage('user', 'World'),
    ]);

    $result = AiObservabilityUtils::aiInputToString($input, FALSE);

    $this->assertSame($input->toString(), $result);
  }

  /**
   * @covers ::aiOutputToString
   */
  public function testAiOutputToStringWithChatOutputAndChatMessage(): void {
    $message = new ChatMessage('user', 'Hello!');
    $output = new ChatOutput($message, ['id' => 'abc'], []);

    $result = AiObservabilityUtils::aiOutputToString($output);
    $decoded = json_decode($result, TRUE, flags: JSON_THROW_ON_ERROR);

    $this->assertSame('user', $decoded['normalized']['role']);
    $this->assertSame('Hello!', $decoded['normalized']['text']);
  }

  /**
   * @covers ::aiOutputToString
   */
  public function testAiOutputToStringLegacyChatOutput(): void {
    $message = new ChatMessage('user', 'Hello!');
    $output = new ChatOutput($message, ['id' => 'abc'], []);

    $result = AiObservabilityUtils::aiOutputToString($output, FALSE);

    $this->assertSame('user: Hello!', $result);
  }

  /**
   * @covers ::aiOutputToString
   */
  public function testAiOutputToStringWithUnsupportedOutput(): void {
    $output = new class () {};
    $result = AiObservabilityUtils::aiOutputToString($output);
    $this->assertStringContainsString('not supported for string conversion', $result);
  }

  /**
   * @covers ::chatMessageToString
   */
  public function testChatMessageToStringWithFiles(): void {
    // Use a stub class with getFileName() method for file mocks.
    $fileStub1 = new class {

      /**
       * Returns the file name for the stub file object.
       *
       * @return string
       *   The file name.
       */
      public function getFileName() {
        return 'file1.txt';
      }

    };
    $fileStub2 = new class {

      /**
       * Returns the file name for the stub file object.
       *
       * @return string
       *   The file name.
       */
      public function getFileName() {
        return 'file2.txt';
      }

    };

    $message = $this->createMock(ChatMessage::class);
    $message->method('getRole')->willReturn('assistant');
    $message->method('getText')->willReturn('Here are your files.');
    $message->method('getFiles')->willReturn([$fileStub1, $fileStub2]);

    $result = AiObservabilityUtils::chatMessageToString($message);
    $this->assertSame('assistant: Here are your files. [Files: file1.txt, file2.txt]', $result);
  }

  /**
   * @covers ::chatMessageToString
   */
  public function testChatMessageToStringWithoutFiles(): void {
    $message = $this->createMock(ChatMessage::class);
    $message->method('getRole')->willReturn('system');
    $message->method('getText')->willReturn('System message.');
    $message->method('getFiles')->willReturn([]);

    $result = AiObservabilityUtils::chatMessageToString($message);
    $this->assertSame('system: System message.', $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataNoTruncation(): void {
    $payload = 'Short payload.';
    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, 100);
    $this->assertSame($payload, $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataWithTruncation(): void {
    $payload = str_repeat('A', 200);
    $maxLength = 50;
    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, $maxLength);
    $this->assertSame($maxLength, strlen($result));
    $this->assertStringContainsString('[...]', $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataWithStructuredJson(): void {
    $payload = json_encode([
      'messages' => array_fill(0, 10, [
        'role' => 'user',
        'text' => str_repeat('A', 400),
      ]),
      'blob' => 'data:image/png;base64,' . str_repeat('A', 500),
    ], JSON_THROW_ON_ERROR);

    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, 1024);
    $decoded = json_decode($result, TRUE, flags: JSON_THROW_ON_ERROR);

    // Default max_list_items 6 -> 3 head + omitted marker + 3 tail = 7 items.
    $this->assertCount(7, $decoded['messages']);
    $this->assertSame(4, $decoded['messages'][3]['_omitted_items']);
    $this->assertStringContainsString('binary-like content omitted', $decoded['messages'][0]['text']);
    $this->assertStringContainsString('data URL omitted', $decoded['blob']);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataWithSummarizeDisabled(): void {
    $payload = json_encode([
      'messages' => array_fill(0, 10, [
        'role' => 'user',
        'text' => str_repeat('A', 400),
      ]),
    ], JSON_THROW_ON_ERROR);

    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, 100000, ['summarize' => FALSE]);

    // Payload is returned verbatim because no leaf-level summarization
    // happens and the length is below the truncation threshold.
    $this->assertSame($payload, $result);
  }

  /**
   * @covers ::summarizeAiPayloadData
   */
  public function testSummarizeAiPayloadDataWithCustomLimits(): void {
    $payload = json_encode([
      'messages' => array_fill(0, 8, [
        'role' => 'user',
        'text' => str_repeat('hello world ', 10),
      ]),
    ], JSON_THROW_ON_ERROR);

    $result = AiObservabilityUtils::summarizeAiPayloadData($payload, 4096, [
      'summarize' => TRUE,
      'max_string_length' => 20,
      'max_list_items' => 4,
      'max_assoc_keys' => 5,
    ]);
    $decoded = json_decode($result, TRUE, flags: JSON_THROW_ON_ERROR);

    // 8 items, max_list_items 4 -> collapsed (2 head + summary + 2 tail = 5).
    $this->assertCount(5, $decoded['messages']);
    $this->assertSame(4, $decoded['messages'][2]['_omitted_items']);
    // Leaf strings longer than max_string_length get the [...] truncation
    // marker (the text contains spaces so the binary-content check skips).
    $this->assertStringContainsString('[...]', $decoded['messages'][0]['text']);
    $this->assertSame(20, mb_strlen($decoded['messages'][0]['text']));
  }

}
