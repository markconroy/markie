<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_assistant_api\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_agents\Event\BuildSystemPromptEvent;
use Drupal\ai_agents\PluginInterfaces\ConfigAiAgentInterface;
use Drupal\ai_assistant_api\Event\AiAssistantPassContextToAgentEvent;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that assistant context reaches agents via the system prompt.
 *
 * @group ai_assistant_api
 */
#[RunTestsInSeparateProcesses]
final class PassContextToAgentSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'key',
    'ai',
    'ai_agents',
    'ai_assistant_api',
  ];

  /**
   * Dispatches the pass-context event for a mocked agent.
   *
   * @param string $agent_id
   *   The agent id the mocked agent reports.
   * @param array $context
   *   The context to pass.
   */
  protected function dispatchPassContext(string $agent_id, array $context): void {
    $agent = $this->createMock(ConfigAiAgentInterface::class);
    $agent->method('getId')->willReturn($agent_id);
    $event = new AiAssistantPassContextToAgentEvent($agent, $context);
    $this->container->get('event_dispatcher')->dispatch($event, AiAssistantPassContextToAgentEvent::EVENT_NAME);
  }

  /**
   * Dispatches the build system prompt event and returns the final prompt.
   *
   * @param string $agent_id
   *   The agent id building its system prompt.
   * @param string $prompt
   *   The initial system prompt.
   *
   * @return string
   *   The system prompt after subscribers ran.
   */
  protected function dispatchBuildSystemPrompt(string $agent_id, string $prompt = 'You are a helpful agent.'): string {
    $event = new BuildSystemPromptEvent($prompt, $agent_id, []);
    $this->container->get('event_dispatcher')->dispatch($event, BuildSystemPromptEvent::EVENT_NAME);
    return $event->getSystemPrompt();
  }

  /**
   * Context passed to an agent ends up in that agent's system prompt.
   */
  public function testContextIsAppendedToSystemPrompt(): void {
    $this->dispatchPassContext('test_agent', ['current_route' => '/node/2']);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('current_route', $prompt);
    $this->assertStringContainsString('/node/2', $prompt);
    $this->assertStringContainsString('You are a helpful agent.', $prompt);
  }

  /**
   * Context is scoped to the agent it was passed to, not sub-agents.
   */
  public function testContextIsNotAppendedForOtherAgents(): void {
    $this->dispatchPassContext('test_agent', ['current_route' => '/node/2']);
    $prompt = $this->dispatchBuildSystemPrompt('sub_agent');
    $this->assertSame('You are a helpful agent.', $prompt);
  }

  /**
   * An empty context leaves the system prompt untouched.
   */
  public function testEmptyContextLeavesPromptUntouched(): void {
    $this->dispatchPassContext('test_agent', []);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertSame('You are a helpful agent.', $prompt);
  }

  /**
   * Non-scalar context values are serialized instead of fataling.
   */
  public function testNonScalarContextValues(): void {
    $this->dispatchPassContext('test_agent', ['entity' => ['type' => 'node', 'id' => 2]]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('entity', $prompt);
    $this->assertStringContainsString('"id":2', $prompt);
  }

  /**
   * A context altered by another subscriber wins over the original one.
   */
  public function testAlteredContextIsUsed(): void {
    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->addListener(AiAssistantPassContextToAgentEvent::EVENT_NAME, function (AiAssistantPassContextToAgentEvent $event): void {
      $context = $event->getContext();
      $context['current_route'] = '/node/3';
      $event->setContext($context);
    }, 100);
    $this->dispatchPassContext('test_agent', ['current_route' => '/node/2']);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('/node/3', $prompt);
    $this->assertStringNotContainsString('/node/2', $prompt);
  }

  /**
   * Token syntax in the context is stripped before it reaches the prompt.
   *
   * The agent runs token replacement over the finished system prompt, so token
   * syntax surviving in frontend-supplied context would be expanded against the
   * site and current user.
   */
  public function testTokenSyntaxIsNeutralized(): void {
    $this->dispatchPassContext('test_agent', [
      'current_route' => '/node/2',
      'injected' => 'before [site:mail] after',
      '[user:name]' => 'value',
    ]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    // Legitimate context is preserved.
    $this->assertStringContainsString('/node/2', $prompt);
    $this->assertStringContainsString('before  after', $prompt);
    // Token syntax is gone from both keys and values.
    $this->assertStringNotContainsString('[site:mail]', $prompt);
    $this->assertStringNotContainsString('[user:name]', $prompt);
  }

  /**
   * Entities in the context are described instead of encoded as JSON.
   *
   * The legacy chat form fills the context with upcast route parameters, which
   * json_encode() reduces to data the agent cannot use.
   */
  public function testEntityContextValuesAreDescribed(): void {
    $this->installEntitySchema('user');
    $user = User::create(['name' => 'context_tester']);
    $user->save();
    $this->dispatchPassContext('test_agent', ['user' => $user]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('- user: user ' . $user->id() . ' (context_tester)', $prompt);
  }

  /**
   * Context values without information are left out of the prompt.
   */
  public function testValuesWithoutInformationAreSkipped(): void {
    $this->dispatchPassContext('test_agent', [
      'current_route' => '/node/2',
      'empty_string' => '',
      'empty_array' => [],
      'nothing' => NULL,
    ]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('- current_route: /node/2', $prompt);
    $this->assertStringNotContainsString('empty_string', $prompt);
    $this->assertStringNotContainsString('empty_array', $prompt);
    $this->assertStringNotContainsString('nothing', $prompt);
  }

  /**
   * A context holding only empty values leaves the prompt untouched.
   */
  public function testContextOfOnlyEmptyValuesLeavesPromptUntouched(): void {
    $this->dispatchPassContext('test_agent', ['empty_string' => '', 'nothing' => NULL]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertSame('You are a helpful agent.', $prompt);
  }

  /**
   * Boolean context values keep their meaning.
   */
  public function testBooleanContextValues(): void {
    $this->dispatchPassContext('test_agent', ['is_front' => FALSE, 'is_admin' => TRUE]);
    $prompt = $this->dispatchBuildSystemPrompt('test_agent');
    $this->assertStringContainsString('- is_front: false', $prompt);
    $this->assertStringContainsString('- is_admin: true', $prompt);
  }

}
