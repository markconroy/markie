<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Unit;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai_agents\PluginBase\AiAgentEntityWrapper;
use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;
use Drupal\ai_assistant_api\Service\AgentRunner;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

// If the optional ai_agents module is not loaded during standalone unit runs,
// define the interface and wrapper stubs so instanceof and typing checks pass.
// phpcs:disable Drupal.Classes.InterfaceName, Drupal.Classes.ClassFileName, Drupal.Commenting
if (!interface_exists(ConfigAiAgentInterface::class)) {
  interface ConfigAiAgentInterfaceStub {
  }
  class_alias(ConfigAiAgentInterfaceStub::class, ConfigAiAgentInterface::class);
}

if (!class_exists(AiAgentEntityWrapper::class)) {
  class AiAgentEntityWrapperStub implements ConfigAiAgentInterface {

    public function setRunnerId(string $id): void {}

    public function setProgressThreadId(string $id): void {}

    public function setChatInput(ChatInput $input): void {}

    public function setAiProvider(mixed $provider): void {}

    public function setModelName(string $model): void {}

    public function setAiConfiguration(array $config): void {}

    public function setCreateDirectly(bool $direct): void {}

    public function setLooped(bool $looped): void {}

    public function determineSolvability(): void {}

    public function isFinished(): bool {
      return TRUE;
    }

    public function toArray(): array {
      return [];
    }

    public function fromArray(array $data): void {}

    public function solve(): mixed {
      return "";
    }

    public function getChatHistory(): array {
      return [];
    }

  }
  class_alias(AiAgentEntityWrapperStub::class, AiAgentEntityWrapper::class);
}
// phpcs:enable

/**
 * Tests streaming behavior in AgentRunner.
 *
 * @coversDefaultClass \Drupal\ai_assistant_api\Service\AgentRunner
 * @group ai_assistant_api
 * @group 3578168
 */
class AgentRunnerStreamingTest extends UnitTestCase {

  /**
   * Tests that streaming flag sets input streamed output and returns iterator.
   *
   * @covers ::runAsAgent
   */
  public function testStreamingSetsInputFlagAndReturnsIterator(): void {
    $streamedIterator = $this->createMock(StreamedChatMessageIteratorInterface::class);

    $capturedInput = NULL;
    $agent = $this->createMock(AiAgentEntityWrapper::class);
    $agent->expects($this->once())
      ->method('setChatInput')
      ->willReturnCallback(function (ChatInput $input) use (&$capturedInput) {
        $capturedInput = $input;
      });
    $agent->method('isFinished')->willReturn(TRUE);
    $agent->method('solve')->willReturn($streamedIterator);

    $aiAgentPluginManager = $this->createMock(PluginManagerInterface::class);
    $aiAgentPluginManager->method('createInstance')
      ->with('streaming_agent')
      ->willReturn($agent);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $tempStore->method('get')->with('job-streaming-1')->willReturn(NULL);
    $tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $tempStoreFactory->method('get')->with('ai_assistant_threads')->willReturn($tempStore);

    $aiProvider = $this->createMock(PluginManagerInterface::class);
    $aiProvider->method('createInstance')->willReturn(new \stdClass());

    $runner = new AgentRunner(
      $aiProvider,
      $tempStoreFactory,
      $this->createMock(EventDispatcherInterface::class),
      $aiAgentPluginManager,
    );

    $history = [
      ['role' => 'user', 'message' => 'Hello streaming agent'],
    ];
    $defaults = [
      'provider_id' => 'test_provider',
      'model_id' => 'test_model',
      'configuration' => [],
    ];

    $output = $runner->runAsAgent('streaming_agent', $history, $defaults, 'job-streaming-1', FALSE, [], TRUE);

    $this->assertInstanceOf(ChatOutput::class, $output);
    $this->assertSame($streamedIterator, $output->getNormalized());
    $this->assertNotNull($capturedInput);
    $this->assertTrue($capturedInput->isStreamedOutput());
  }

  /**
   * Tests that completed streams clean up persisted agent state.
   *
   * @covers ::runAsAgent
   */
  public function testStreamingCleansUpAgentStateAfterIteratorCompletes(): void {
    $streamedIterator = $this->createMock(StreamedChatMessageIteratorInterface::class);
    $completionCallback = NULL;
    $streamedIterator->expects($this->once())
      ->method('addCallback')
      ->willReturnCallback(function (callable $callback) use (&$completionCallback): void {
        $completionCallback = $callback;
      });

    $agent = $this->createMock(AiAgentEntityWrapper::class);
    $agent->expects($this->once())
      ->method('isFinished')
      ->willReturn(TRUE);
    $agent->method('solve')->willReturn($streamedIterator);

    $aiAgentPluginManager = $this->createMock(PluginManagerInterface::class);
    $aiAgentPluginManager->method('createInstance')
      ->with('streaming_agent')
      ->willReturn($agent);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $tempStore->method('get')->with('job-streaming-2')->willReturn(NULL);
    $tempStore->expects($this->once())
      ->method('delete')
      ->with('job-streaming-2');
    $tempStore->expects($this->never())->method('set');
    $tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $tempStoreFactory->method('get')->with('ai_assistant_threads')->willReturn($tempStore);

    $aiProvider = $this->createMock(PluginManagerInterface::class);
    $aiProvider->method('createInstance')->willReturn(new \stdClass());

    $runner = new AgentRunner(
      $aiProvider,
      $tempStoreFactory,
      $this->createMock(EventDispatcherInterface::class),
      $aiAgentPluginManager,
    );

    $history = [
      ['role' => 'user', 'message' => 'Hello streaming agent'],
    ];
    $defaults = [
      'provider_id' => 'test_provider',
      'model_id' => 'test_model',
      'configuration' => [],
    ];

    $output = $runner->runAsAgent('streaming_agent', $history, $defaults, 'job-streaming-2', FALSE, [], TRUE);

    $this->assertSame($streamedIterator, $output->getNormalized());
    $this->assertIsCallable($completionCallback);
    $completionCallback(new ChatMessage('assistant', 'Finished response'));
  }

  /**
   * Tests that non-streaming flag leaves input streamed output false.
   *
   * @covers ::runAsAgent
   */
  public function testNonStreamingLeavesInputFlagFalseAndReturnsMessage(): void {
    $capturedInput = NULL;
    $agent = $this->createMock(AiAgentEntityWrapper::class);
    $agent->expects($this->once())
      ->method('setChatInput')
      ->willReturnCallback(function (ChatInput $input) use (&$capturedInput) {
        $capturedInput = $input;
      });
    $agent->method('isFinished')->willReturn(TRUE);
    $agent->method('solve')->willReturn('Plain text response');
    $agent->method('getChatHistory')->willReturn([]);

    $aiAgentPluginManager = $this->createMock(PluginManagerInterface::class);
    $aiAgentPluginManager->method('createInstance')
      ->with('non_streaming_agent')
      ->willReturn($agent);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $tempStore->method('get')->with('job-non-streaming-1')->willReturn(NULL);
    $tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $tempStoreFactory->method('get')->with('ai_assistant_threads')->willReturn($tempStore);

    $aiProvider = $this->createMock(PluginManagerInterface::class);
    $aiProvider->method('createInstance')->willReturn(new \stdClass());

    $runner = new AgentRunner(
      $aiProvider,
      $tempStoreFactory,
      $this->createMock(EventDispatcherInterface::class),
      $aiAgentPluginManager,
    );

    $history = [
      ['role' => 'user', 'message' => 'Hello non-streaming agent'],
    ];
    $defaults = [
      'provider_id' => 'test_provider',
      'model_id' => 'test_model',
      'configuration' => [],
    ];

    $output = $runner->runAsAgent('non_streaming_agent', $history, $defaults, 'job-non-streaming-1', FALSE, [], FALSE);

    $this->assertInstanceOf(ChatOutput::class, $output);
    $this->assertInstanceOf(ChatMessage::class, $output->getNormalized());
    $this->assertSame('Plain text response', $output->getNormalized()->getText());
    $this->assertNotNull($capturedInput);
    $this->assertFalse($capturedInput->isStreamedOutput());
  }

}
