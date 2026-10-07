<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Plugin\AiGuardrail;

use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;
use Drupal\ai\Plugin\AiGuardrail\RestrictToTopic;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests the RestrictToTopic guardrail.
 *
 * Covers the pre-LLM exit paths (non-chat input and chat input without user
 * messages) as well as the pure-logic helper additions that do not require an
 * LLM call: `bucketTopics()` (exact and semantic modes) and `buildPrompt()`.
 *
 * The classifier branch is exercised through integration tests because it
 * requires a real AI provider. The selection logic is covered by
 * \Drupal\Tests\ai\Unit\Guardrail\UserMessageSelectionTraitTest.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\RestrictToTopic
 */
class RestrictToTopicTest extends TestCase {

  /**
   * Builds a configured RestrictToTopic instance.
   */
  protected function createGuardrail(array $configuration): RestrictToTopic {
    return new RestrictToTopic(
      $configuration,
      'restrict_to_topic',
      ['label' => 'Restrict to Topic'],
      $this->createStub(PromptJsonDecoderInterface::class),
    );
  }

  /**
   * Build a plugin instance for invoking protected helpers via reflection.
   */
  protected function createPlugin(array $configuration = []): RestrictToTopic {
    return new RestrictToTopic(
      $configuration,
      'restrict_to_topic',
      [
        'id' => 'restrict_to_topic',
        'label' => 'Restrict to Topic',
      ],
      $this->createMock(PromptJsonDecoderInterface::class),
    );
  }

  /**
   * Non-chat input short-circuits to a pass before any LLM call.
   */
  public function testNonChatInputPasses(): void {
    $guardrail = $this->createGuardrail([
      'valid_topics' => 'sports',
      'invalid_topics' => 'politics',
    ]);
    $input = new EmbeddingsInput('some text');

    $this->assertInstanceOf(PassResult::class, $guardrail->processInput($input));
  }

  /**
   * Chat input with no user messages short-circuits to a pass.
   */
  public function testNoUserMessagesPasses(): void {
    $guardrail = $this->createGuardrail([
      'valid_topics' => 'sports',
      'invalid_topics' => 'politics',
    ]);
    $input = new ChatInput([
      new ChatMessage('assistant', 'hello'),
      new ChatMessage('tool', 'result'),
    ]);

    $this->assertInstanceOf(PassResult::class, $guardrail->processInput($input));
  }

  /**
   * Invoke the protected bucketTopics() helper.
   */
  protected function invokeBucket(RestrictToTopic $plugin, array $topics_present, array $valid, array $invalid, string $mode, float $threshold): array {
    $method = new \ReflectionMethod($plugin, 'bucketTopics');
    $method->setAccessible(TRUE);
    return $method->invoke($plugin, $topics_present, $valid, $invalid, $mode, $threshold);
  }

  /**
   * Invoke the protected buildPrompt() helper.
   */
  protected function invokePrompt(RestrictToTopic $plugin, string $text, array $all_topics, string $mode): string {
    $method = new \ReflectionMethod($plugin, 'buildPrompt');
    $method->setAccessible(TRUE);
    return $method->invoke($plugin, $text, $all_topics, $mode);
  }

  /**
   * Exact mode: verbatim matches bucket correctly, drift lands in unmatched.
   */
  public function testExactModeBuckets(): void {
    $plugin = $this->createPlugin();

    $result = $this->invokeBucket(
      $plugin,
      ['banana', 'weapons', 'banana fruit'],
      ['banana', 'apple'],
      ['weapons'],
      'exact',
      0.75,
    );

    $this->assertSame(['banana'], $result['valid_found']);
    $this->assertSame(['weapons'], $result['invalid_found']);
    $this->assertSame(['banana fruit'], $result['unmatched']);
  }

  /**
   * Semantic mode: pluralized drift is mapped to the configured topic.
   *
   * `bananas` vs `banana` scores ~92% via similar_text(), comfortably above
   * the default 0.75 threshold.
   */
  public function testSemanticModeMapsDriftToValidBucket(): void {
    $plugin = $this->createPlugin();

    $result = $this->invokeBucket(
      $plugin,
      ['bananas'],
      ['banana'],
      [],
      'semantic',
      0.75,
    );

    $this->assertSame(['bananas'], $result['valid_found']);
    $this->assertSame([], $result['invalid_found']);
    $this->assertSame([], $result['unmatched']);
  }

  /**
   * Semantic mode: a low-scoring qualifier falls below the default threshold.
   *
   * `banana fruit` vs `banana` scores ~67% via similar_text(); at the default
   * 0.75 threshold it must be recorded as unmatched rather than silently
   * bucketed. This is the drift case the issue flags as the current silent
   * bypass.
   */
  public function testSemanticModeBananaFruitBelowDefaultThreshold(): void {
    $plugin = $this->createPlugin();

    $result = $this->invokeBucket(
      $plugin,
      ['banana fruit'],
      ['banana'],
      [],
      'semantic',
      0.75,
    );

    $this->assertSame([], $result['valid_found']);
    $this->assertSame(['banana fruit'], $result['unmatched']);
  }

  /**
   * Semantic mode: truly unrelated topics still land in unmatched.
   */
  public function testSemanticModeRecordsUnmatched(): void {
    $plugin = $this->createPlugin();

    $result = $this->invokeBucket(
      $plugin,
      ['motorcycle'],
      ['banana'],
      ['weapons'],
      'semantic',
      0.75,
    );

    $this->assertSame([], $result['valid_found']);
    $this->assertSame([], $result['invalid_found']);
    $this->assertSame(['motorcycle'], $result['unmatched']);
  }

  /**
   * Semantic mode: threshold governs whether drift is accepted.
   *
   * `banana fruit` vs `banana` scores ~67%. At a 0.99 threshold it is
   * rejected; at 0.60 it is accepted.
   */
  public function testSemanticModeHonoursThreshold(): void {
    $plugin = $this->createPlugin();

    $strict = $this->invokeBucket(
      $plugin,
      ['banana fruit'],
      ['banana'],
      [],
      'semantic',
      0.99,
    );
    $this->assertSame(['banana fruit'], $strict['unmatched']);
    $this->assertSame([], $strict['valid_found']);

    $loose = $this->invokeBucket(
      $plugin,
      ['banana fruit'],
      ['banana'],
      [],
      'semantic',
      0.60,
    );
    $this->assertSame(['banana fruit'], $loose['valid_found']);
    $this->assertSame([], $loose['unmatched']);
  }

  /**
   * Semantic mode prefers the highest-scoring configured topic across lists.
   */
  public function testSemanticModePicksBestBucketAcrossValidAndInvalid(): void {
    $plugin = $this->createPlugin();

    $result = $this->invokeBucket(
      $plugin,
      ['weapon'],
      ['banana'],
      ['weapons'],
      'semantic',
      0.75,
    );

    $this->assertSame([], $result['valid_found']);
    $this->assertSame(['weapon'], $result['invalid_found']);
    $this->assertSame([], $result['unmatched']);
  }

  /**
   * Exact mode prompt keeps the original wording.
   */
  public function testExactPromptWording(): void {
    $plugin = $this->createPlugin();

    $prompt = $this->invokePrompt($plugin, 'hello', ['banana'], 'exact');

    $this->assertStringContainsString('return a valid json list', $prompt);
    $this->assertStringNotContainsString('verbatim', $prompt);
  }

  /**
   * Semantic mode prompt instructs the model to return list strings verbatim.
   */
  public function testSemanticPromptForcesVerbatim(): void {
    $plugin = $this->createPlugin();

    $prompt = $this->invokePrompt($plugin, 'hello', ['banana'], 'semantic');

    $this->assertStringContainsString('verbatim', $prompt);
    $this->assertStringContainsString('Do not invent', $prompt);
  }

}
