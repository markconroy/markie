<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Tests\UnitTestCase;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Service\HostnameFilter;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_assistant_api\AiAssistantActionPluginManager;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_assistant_api\Service\AssistantMessageBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Tests that AiAssistantApiRunner forwards the streaming flag to agents.
 *
 * Regression test for the bug where AiAssistantApiRunner::process() would
 * delegate to AgentRunner::runAsAgent() without passing $this->streaming,
 * so enabling streaming had no effect for agent-backed assistants.
 *
 * @coversDefaultClass \Drupal\ai_assistant_api\AiAssistantApiRunner
 * @group ai_assistant_api
 * @group 3578168
 */
class AiAssistantApiRunnerAgentStreamingTest extends UnitTestCase {

  /**
   * @covers ::process
   */
  public function testStreamingIsForwardedToAgentRunner(): void {
    $this->assertStreamingForwarded(TRUE);
  }

  /**
   * @covers ::process
   */
  public function testNonStreamingIsForwardedToAgentRunner(): void {
    $this->assertStreamingForwarded(FALSE);
  }

  /**
   * Builds a runner for an agent-backed assistant and asserts forwarding.
   *
   * @param bool $streaming
   *   The streaming flag to set on the runner before calling process().
   */
  protected function assertStreamingForwarded(bool $streaming): void {
    // A spy standing in for the ai_assistant_api.agent_runner service. The
    // real service call is duck typed (AiAssistantApiRunner::process() only
    // ever calls ->runAsAgent() on it), so no interface is required here.
    $spy = new class() {

      /**
       * Recorded calls.
       *
       * @var array
       */
      public array $calls = [];

      /**
       * Runs as agent.
       *
       * @param mixed ...$args
       *   Arguments.
       *
       * @return \Drupal\ai\OperationType\Chat\ChatOutput
       *   The chat output.
       */
      public function runAsAgent(...$args): ChatOutput {
        $this->calls[] = $args;
        return new ChatOutput(new ChatMessage('assistant', 'stub-response'), [], []);
      }

    };

    $container = new ContainerBuilder();
    $container->set('ai_assistant_api.agent_runner', $spy);
    \Drupal::setContainer($container);

    $moduleHandler = $this->createMock(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')
      ->with('ai_agents')
      ->willReturn(TRUE);

    $currentUser = $this->createMock(AccountProxyInterface::class);
    $currentUser->method('id')->willReturn(1);

    $tempStore = $this->createMock(PrivateTempStore::class);
    $tempStoreFactory = $this->createMock(PrivateTempStoreFactory::class);
    $tempStoreFactory->method('get')->willReturn($tempStore);

    $runner = new AiAssistantApiRunner(
      $this->createMock(EntityTypeManagerInterface::class),
      $this->buildAiProviderPluginManager(),
      $this->createMock(Renderer::class),
      $tempStoreFactory,
      $this->buildAiAssistantActionPluginManager(),
      $this->createMock(EventDispatcherInterface::class),
      $currentUser,
      $this->createMock(LoggerChannelFactoryInterface::class),
      $this->createMock(PromptJsonDecoderInterface::class),
      $this->createMock(AssistantMessageBuilder::class),
      $this->createMock(SessionManagerInterface::class),
      new Settings([]),
      $moduleHandler,
    );

    // The llm_provider is deliberately not '__default__' so that
    // getProviderAndModel() never has to call into the (mocked, unused)
    // provider plugin manager.
    $assistant = new AiAssistant([
      'id' => 'test_assistant',
      'ai_agent' => 'stub_agent',
      'llm_provider' => 'echoai',
      'llm_model' => 'test-model',
      'allow_history' => 'none',
      'roles' => [],
    ], 'ai_assistant');

    $runner->setAssistant($assistant);
    $runner->setUserMessage(new UserMessage('Hello there'));
    $runner->streamedOutput($streaming);

    $output = $runner->process();

    $this->assertInstanceOf(ChatOutput::class, $output);
    $this->assertCount(1, $spy->calls);
    // runAsAgent()'s signature is ($assistant_id, $chat_history, $defaults,
    // $job_id, $verbose_mode, $context, $streaming): the 7th argument
    // (index 6) is the streaming flag.
    $this->assertSame($streaming, $spy->calls[0][6]);
  }

  /**
   * Builds a minimal, functional AiProviderPluginManager.
   *
   * AiProviderPluginManager is a final class and cannot be mocked, so a real
   * instance is constructed with mocked collaborators. The agent-backed code
   * path under test never actually calls into it (the llm_provider used is
   * not '__default__'), so those collaborators are never invoked.
   *
   * @return \Drupal\ai\AiProviderPluginManager
   *   The provider plugin manager.
   */
  protected function buildAiProviderPluginManager(): AiProviderPluginManager {
    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')->willReturnCallback(function (string $id) {
      return match ($id) {
        'event_dispatcher' => $this->createMock(EventDispatcherInterface::class),
        'logger.factory' => $this->createMock(LoggerChannelFactoryInterface::class),
        'config.factory' => $this->createMock(ConfigFactoryInterface::class),
        default => NULL,
      };
    });

    return new AiProviderPluginManager(
      new \ArrayIterator([]),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
      $container,
      $this->createMock(MessengerInterface::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(HostnameFilter::class),
    );
  }

  /**
   * Builds a minimal, functional AiAssistantActionPluginManager.
   *
   * AiAssistantActionPluginManager is a final class and cannot be mocked;
   * it is never invoked in the agent-backed code path under test.
   *
   * @return \Drupal\ai_assistant_api\AiAssistantActionPluginManager
   *   The action plugin manager.
   */
  protected function buildAiAssistantActionPluginManager(): AiAssistantActionPluginManager {
    return new AiAssistantActionPluginManager(
      new \ArrayIterator([]),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(ModuleHandlerInterface::class),
    );
  }

}
