<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\EventSubscriber;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the global guardrails setting.
 *
 * @group ai
 * @covers \Drupal\ai\EventSubscriber\GlobalGuardrailsEventSubscriber
 *
 * @see https://www.drupal.org/project/ai/issues/3584851
 */
class GlobalGuardrailsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai']);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Global set applies when the input has no set attached.
   */
  public function testGlobalSetAppliesWithoutInputSet(): void {
    $this->createSet('global_a', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['global_a']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    // No stop, normal echo passes through.
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
  }

  /**
   * Global set runs in addition to an input-attached set.
   */
  public function testGlobalSetCombinesWithInputSet(): void {
    $this->createSet('input_set', 'stub_input', 'pass');
    $this->createSet('global_set', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['global_set']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('input_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_input'), 'Input-attached set ran.');
    $this->assertSame(1, $this->invocations('stub_global'), 'Global set ran.');
  }

  /**
   * A global set crossing its threshold blocks the request.
   */
  public function testGlobalSetStopThresholdBlocks(): void {
    $this->createSet('global_block', 'stub_global', 'stop', 'Blocked globally');
    $this->setGlobalGuardrails(['global_block']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked globally', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
  }

  /**
   * Missing / unknown global set ids are silently ignored.
   */
  public function testUnknownGlobalSetIdsAreIgnored(): void {
    $this->setGlobalGuardrails(['does_not_exist']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
  }

  /**
   * A global rewrite-input guardrail mutates the prompt before the provider.
   */
  public function testGlobalSetRewritesInputBeforeProvider(): void {
    $this->createSet('global_rewrite', 'stub_global', 'rewrite_input', 'Sanitized prompt');
    $this->setGlobalGuardrails(['global_rewrite']);

    $input = new ChatInput([new ChatMessage('user', 'Original prompt')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Sanitized prompt', $result->getNormalized()->getText());
    $this->assertStringNotContainsString('Original prompt', $result->getNormalized()->getText());
  }

  /**
   * A global rewrite-output guardrail mutates the provider response.
   */
  public function testGlobalSetRewritesOutput(): void {
    $this->createSetPostGenerate('global_out_rewrite', 'stub_global', 'rewrite_output', 'Redacted');
    $this->setGlobalGuardrails(['global_out_rewrite']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertSame('Redacted', $result->getNormalized()->getText());
  }

  /**
   * Regression guard for #3584851: globals run before caller-attached sets.
   *
   * A caller-attached RewriteInputResult must not reach the provider without
   * the global set having evaluated the original, un-mutated prompt first.
   */
  public function testGlobalRewriteRunsBeforeCallerRewrite(): void {
    // Global: observes the prompt and rewrites it to a sentinel value.
    $this->createSet('global_first', 'stub_global', 'rewrite_input', 'GLOBAL_REWROTE');
    // Caller-attached: would also rewrite if it got to run first.
    $this->createSet('caller_set', 'stub_caller', 'rewrite_input', 'Caller rewrote');
    $this->setGlobalGuardrails(['global_first']);

    $input = new ChatInput([new ChatMessage('user', 'Original prompt')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('caller_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);

    // The global ran first and saw the ORIGINAL prompt — this is the
    // load-bearing assertion. Before the ordering fix this was 'Original
    // prompt' only if the global happened to be first, which it was not.
    $this->assertSame(
      'Original prompt',
      \Drupal::state()->get('ai_test.counting_stub.stub_global.seen'),
      'Global guardrail must evaluate the original prompt before any caller rewrite.',
    );

    // Caller ran second and saw what the global wrote.
    $this->assertSame(
      'GLOBAL_REWROTE',
      \Drupal::state()->get('ai_test.counting_stub.stub_caller.seen'),
    );

    // Last writer wins on the final ChatMessage, so the caller's rewrite
    // is what the provider echoed back.
    $this->assertStringContainsString('Caller rewrote', $result->getNormalized()->getText());
  }

  /**
   * A global stop short-circuits caller-attached sets entirely.
   */
  public function testGlobalStopPreventsCallerSetsFromRunning(): void {
    $this->createSet('global_block', 'stub_global', 'stop', 'Blocked globally');
    $this->createSet('caller_set', 'stub_caller', 'pass');
    $this->setGlobalGuardrails(['global_block']);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('caller_set', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Blocked globally', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_global'));
    $this->assertSame(0, $this->invocations('stub_caller'), 'Caller set must not run after a global stop.');
  }

  /**
   * Deleting a globally-configured set leaves subsequent requests working.
   *
   * Covers the resilience path in GlobalGuardrailsEventSubscriber where
   * getGuardrailSetById() returns NULL — the subscriber must skip the missing
   * set and let the request proceed normally.
   */
  public function testDeletedGlobalSetLeavesRequestsWorking(): void {
    $this->createSet('doomed_global', 'stub_global', 'pass');
    $this->setGlobalGuardrails(['doomed_global']);

    AiGuardrailSet::load('doomed_global')->delete();

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(0, $this->invocations('stub_global'), 'Deleted global set must not execute its guardrail.');
  }

  /**
   * Deleting a set auto-cleans its id from ai.settings:global_guardrails.
   *
   * Covers ai_ai_guardrail_set_delete() — on entity delete, the stale id is
   * removed from config so admins who never revisit the form do not carry a
   * dead reference.
   */
  public function testDeletingSetRemovesItFromGlobals(): void {
    $this->createSet('auto_clean_global', 'stub_global', 'pass');
    $this->createSet('kept_global', 'stub_kept', 'pass');
    $this->setGlobalGuardrails(['auto_clean_global', 'kept_global']);

    AiGuardrailSet::load('auto_clean_global')->delete();

    $this->assertSame(
      ['kept_global'],
      $this->config('ai.settings')->get('global_guardrails'),
      'Deleted set id must be removed from global_guardrails; other ids are preserved.',
    );
  }

  /**
   * Empty global guardrails config leaves behavior unchanged.
   */
  public function testEmptyGlobalConfigIsNoop(): void {
    $this->createSet('input_only', 'stub_input', 'pass');
    $this->setGlobalGuardrails([]);

    $input = new ChatInput([new ChatMessage('user', 'Hi')]);
    $helper = \Drupal::service('ai.guardrail_helper');
    $input = $helper->applyGuardrailSetToChatInput('input_only', $input);

    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $result = $provider->chat($input, 'gpt-test', ['test']);

    $this->assertInstanceOf(ChatOutput::class, $result);
    $this->assertStringContainsString('Hi', $result->getNormalized()->getText());
    $this->assertSame(1, $this->invocations('stub_input'));
  }

  /**
   * Create a guardrail entity + set wrapping the counting stub plugin.
   *
   * The stub is wired into the pre-generate slot. Use
   * ::createSetPostGenerate() for post-generate tests.
   */
  private function createSet(string $set_id, string $tag, string $mode, string $message = 'stub', float $score = 1.0, float $threshold = 1.0): void {
    $this->createSetInternal($set_id, $tag, $mode, $message, $score, $threshold, 'pre');
  }

  /**
   * Create a set with the stub wired into the post-generate slot.
   */
  private function createSetPostGenerate(string $set_id, string $tag, string $mode, string $message = 'stub', float $score = 1.0, float $threshold = 1.0): void {
    $this->createSetInternal($set_id, $tag, $mode, $message, $score, $threshold, 'post');
  }

  /**
   * Shared implementation for the two createSet helpers.
   */
  private function createSetInternal(string $set_id, string $tag, string $mode, string $message, float $score, float $threshold, string $phase): void {
    $guardrail = AiGuardrail::create([
      'id' => $set_id . '_guardrail',
      'label' => $set_id . ' guardrail',
      'description' => 'test stub',
      'guardrail' => 'counting_stub_guardrail',
      'guardrail_settings' => [
        'tag' => $tag,
        'mode' => $mode,
        'message' => $message,
        'score' => $score,
      ],
    ]);
    $guardrail->save();

    $pre = $phase === 'pre' ? [$set_id . '_guardrail'] : [];
    $post = $phase === 'post' ? [$set_id . '_guardrail'] : [];
    $set = AiGuardrailSet::create([
      'id' => $set_id,
      'label' => $set_id,
      'description' => 'test set',
      'stop_threshold' => $threshold,
      'pre_generate_guardrails' => ['plugin_id' => $pre],
      'post_generate_guardrails' => ['plugin_id' => $post],
    ]);
    $set->save();
  }

  /**
   * Sets the global guardrails configuration.
   */
  private function setGlobalGuardrails(array $ids): void {
    $this->config('ai.settings')->set('global_guardrails', $ids)->save();
  }

  /**
   * Returns how many times a tagged stub has been invoked.
   */
  private function invocations(string $tag): int {
    return (int) \Drupal::state()->get('ai_test.counting_stub.' . $tag, 0);
  }

}
