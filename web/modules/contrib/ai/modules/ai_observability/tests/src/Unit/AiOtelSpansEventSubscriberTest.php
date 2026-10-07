<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIterator;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\EventSubscriber\AiOtelSpansEventSubscriber;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\opentelemetry\OpentelemetryService;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;

/**
 * Tests AI OpenTelemetry spans event subscriber.
 *
 * @group ai_observability
 */
class AiOtelSpansEventSubscriberTest extends UnitTestCase {

  /**
   * Storage for OpenTelemetry spans created during tests.
   *
   * @var \ArrayObject
   */
  protected \ArrayObject $spanStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->spanStorage = new \ArrayObject();
  }

  /**
   * Tests that no spans are created when OTEL is disabled.
   */
  public function testWithOtelDisabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => FALSE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(0, $this->spanStorage);
  }

  /**
   * Tests that OpenTelemetry spans are created when OTEL is enabled.
   */
  public function testWithOtelSpansEnabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('AI provider request', $span->getName());
    // Ad-hoc attributes.
    $this->assertEquals('test-provider', $span->getAttributes()->get('provider'));
    $this->assertEquals('test-operation', $span->getAttributes()->get('operation_type'));
    $this->assertEquals('test-model', $span->getAttributes()->get('model'));
    // GenAI semantic-convention attributes.
    $this->assertEquals('test-provider', $span->getAttributes()->get('gen_ai.provider.name'));
    $this->assertEquals('test-model', $span->getAttributes()->get('gen_ai.request.model'));
    // 'test-operation' is non-standard; gen_ai.operation.name must be omitted.
    $this->assertNull($span->getAttributes()->get('gen_ai.operation.name'));
  }

  /**
   * Tests that gen_ai.usage.* preserves explicit zero values.
   *
   * Array_filter() on the ad-hoc token_usage drops zeros; the gen_ai.*
   * scalar attributes must keep them.
   */
  public function testGenAiTokenUsagePreservesZeros() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'zero-thread-id',
      providerId: 'test-provider',
      operationType: 'test-operation',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'zero-thread-id',
      providerId: 'test-provider',
      operationType: 'test-operation',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        [],
        [],
        new TokenUsageDto(input: 0, output: 0),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];

    // GenAI scalars must preserve explicit zero.
    $this->assertSame(0, $span->getAttributes()->get('gen_ai.usage.input_tokens'));
    $this->assertSame(0, $span->getAttributes()->get('gen_ai.usage.output_tokens'));

    // Ad-hoc token_usage array_filter must have dropped the zeros.
    $tokenUsage = $span->getAttributes()->get('token_usage');
    $this->assertIsArray($tokenUsage);
    $this->assertArrayNotHasKey('input', $tokenUsage);
    $this->assertArrayNotHasKey('output', $tokenUsage);
  }

  /**
   * Tests gen_ai.response.model and gen_ai.response.finish_reasons (OpenAI).
   */
  public function testGenAiResponseAttributesOpenAiShape() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'openai-thread-id',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'gpt-4o-mini',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'openai-thread-id',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'gpt-4o-mini',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        ['model' => 'gpt-4o-mini', 'choices' => [['finish_reason' => 'stop']]],
        [],
        new TokenUsageDto(input: 1, output: 1),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('gpt-4o-mini', $span->getAttributes()->get('gen_ai.response.model'));
    $this->assertEquals(['stop'], $span->getAttributes()->get('gen_ai.response.finish_reasons'));
  }

  /**
   * Tests gen_ai.response.model and gen_ai.response.finish_reasons (Anthropic).
   */
  public function testGenAiResponseAttributesAnthropicShape() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'anthropic-thread-id',
      providerId: 'anthropic',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'claude-3-5-sonnet',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'anthropic-thread-id',
      providerId: 'anthropic',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'claude-3-5-sonnet',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        ['model' => 'claude-3-5-sonnet', 'stop_reason' => 'end_turn'],
        [],
        new TokenUsageDto(input: 1, output: 1),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('claude-3-5-sonnet', $span->getAttributes()->get('gen_ai.response.model'));
    $this->assertEquals(['end_turn'], $span->getAttributes()->get('gen_ai.response.finish_reasons'));
  }

  /**
   * Tests that an empty Anthropic stop_reason does not emit finish_reasons.
   *
   * Regression for the empty-string bug: when the Anthropic raw output contains
   * stop_reason => '' (empty string), gen_ai.response.finish_reasons must be
   * left unset rather than emitting an array containing an empty string.
   */
  public function testGenAiResponseFinishReasonsAnthropicEmptyStopReason() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'anthropic-empty-thread-id',
      providerId: 'anthropic',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'claude-3-5-sonnet',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'anthropic-empty-thread-id',
      providerId: 'anthropic',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'claude-3-5-sonnet',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        ['model' => 'claude-3-5-sonnet', 'stop_reason' => ''],
        [],
        new TokenUsageDto(input: 1, output: 1),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertNull(
      $span->getAttributes()->get('gen_ai.response.finish_reasons'),
      'Empty stop_reason must not emit gen_ai.response.finish_reasons.'
    );
  }

  /**
   * Tests that missing model/choices keys leave response attributes unset.
   *
   * Regression for m4: a raw output array without a 'model' key and without
   * choices or stop_reason must not set gen_ai.response.model or
   * gen_ai.response.finish_reasons.
   */
  public function testGenAiResponseAttributesAbsentWhenNoModelOrReasons() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'no-model-thread-id',
      providerId: 'test-provider',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'no-model-thread-id',
      providerId: 'test-provider',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        ['usage' => ['x' => 1]],
        [],
        new TokenUsageDto(input: 1, output: 1),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertNull($span->getAttributes()->get('gen_ai.response.model'));
    $this->assertNull($span->getAttributes()->get('gen_ai.response.finish_reasons'));
  }

  /**
   * Tests finish reasons are collected from all choices (multi-choice / n > 1).
   *
   * Regression for I1: the OpenAI branch must gather finish_reason from every
   * element of $raw['choices'], not just choices[0].
   */
  public function testGenAiResponseFinishReasonsMultiChoice() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'multi-choice-thread-id',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'gpt-4o',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'multi-choice-thread-id',
      providerId: 'openai',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'gpt-4o',
      output: new ChatOutput(
        new ChatMessage('agent', 'x'),
        ['choices' => [['finish_reason' => 'stop'], ['finish_reason' => 'length']]],
        [],
        new TokenUsageDto(input: 1, output: 1),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertSame(['stop', 'length'], $span->getAttributes()->get('gen_ai.response.finish_reasons'));
  }

  /**
   * Tests gen_ai.operation.name mapping: well-known mapped, unknown omitted.
   */
  public function testGenAiOperationNameMapping() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    // Test 1: well-known operation 'chat' must map to 'chat'.
    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = new PreGenerateResponseEvent(
      requestThreadId: 'chat-thread-id',
      providerId: 'test-provider',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    $post = new PostGenerateResponseEvent(
      requestThreadId: 'chat-thread-id',
      providerId: 'test-provider',
      operationType: 'chat',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput(new ChatMessage('agent', 'x'), [], [], new TokenUsageDto()),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $post);

    $this->assertCount(1, $this->spanStorage);
    $this->assertEquals('chat', $this->spanStorage[0]->getAttributes()->get('gen_ai.operation.name'));

    // Test 2: non-standard operation 'text_to_image' must be omitted.
    $service2 = $this->initAiOtelSpansEventSubscriberService();

    $pre2 = new PreGenerateResponseEvent(
      requestThreadId: 'image-thread-id',
      providerId: 'test-provider',
      operationType: 'text_to_image',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service2, PreGenerateResponseEvent::EVENT_NAME, $pre2);

    $post2 = new PostGenerateResponseEvent(
      requestThreadId: 'image-thread-id',
      providerId: 'test-provider',
      operationType: 'text_to_image',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput(new ChatMessage('agent', 'x'), [], [], new TokenUsageDto()),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service2, PostGenerateResponseEvent::EVENT_NAME, $post2);

    $this->assertCount(2, $this->spanStorage);
    $this->assertNull($this->spanStorage[1]->getAttributes()->get('gen_ai.operation.name'));
  }

  /**
   * Tests that spans are properly completed with streaming events.
   */
  public function testSpanWithStreamingEvent() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostStreamingResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostStreamingResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertEquals('AI provider request', $span->getName());
  }

  /**
   * Tests that streaming spans record the final token usage.
   *
   * Reproduces the streaming defect: the span is ended on the initial
   * PostGenerateResponseEvent, which carries the un-consumed iterator and so
   * empty token usage. The terminal PostStreamingResponseEvent carries the
   * reconstructed output with the real usage, but the span is already ended, so
   * the re-filled attributes are dropped.
   */
  public function testStreamingSpanRecordsFinalTokenUsage() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    // Pre-generate: start and store the span.
    $pre = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $pre);

    // Post-generate: the stream is not yet consumed, so the normalized value is
    // the iterator and the token usage is still empty. Same request thread id.
    $streamed = new class(new \ArrayIterator([])) extends StreamedChatMessageIterator {};
    $postGenerate = new PostGenerateResponseEvent(
      requestThreadId: 'test-thread-id',
      providerId: 'test-provider',
      operationType: 'test-operation',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput($streamed, [], [], new TokenUsageDto()),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $postGenerate);

    // Post-streaming: the stream has finished; the reconstructed output now
    // carries the final token usage. Same request thread id.
    $postStreaming = new PostStreamingResponseEvent(
      requestThreadId: 'test-thread-id',
      providerId: 'test-provider',
      operationType: 'test-operation',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput(
        new ChatMessage('agent', 'response'),
        [],
        [],
        new TokenUsageDto(input: 10, output: 20, total: 30),
      ),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber($service, PostStreamingResponseEvent::EVENT_NAME, $postStreaming);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $tokenUsage = $span->getAttributes()->get('token_usage');
    $this->assertIsArray($tokenUsage, 'Streaming span should expose token usage.');
    $this->assertSame(10, $tokenUsage['input'] ?? NULL, 'Streaming span must record final input token usage.');
    $this->assertSame(20, $tokenUsage['output'] ?? NULL, 'Streaming span must record final output token usage.');
  }

  /**
   * Tests that unfinished streaming spans are ended during destruction.
   */
  public function testStreamingSpanFinalizedOnDestructWithoutTerminalEvent() {
    $configFactory = TestHelpers::service('config.factory');
    $configFactory->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
    ]);

    $otelService = TestHelpers::service(
      'opentelemetry',
      OpentelemetryService::class,
    );
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub(
      $this->spanStorage,
    );
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $pre = AiObservabilityTestHelper::getAiEventStub(
      PreGenerateResponseEvent::class,
    );
    TestHelpers::callEventSubscriber(
      $service,
      PreGenerateResponseEvent::EVENT_NAME,
      $pre,
    );

    $streamed = new class(
      new \ArrayIterator([]),
    ) extends StreamedChatMessageIterator {
    };
    $postGenerate = new PostGenerateResponseEvent(
      requestThreadId: 'test-thread-id',
      providerId: 'test-provider',
      operationType: 'test-operation',
      configuration: [],
      input: AiObservabilityTestHelper::getInputStub(),
      modelId: 'test-model',
      output: new ChatOutput($streamed, [], [], new TokenUsageDto()),
      tags: [],
      debugData: [],
      metadata: [],
    );
    TestHelpers::callEventSubscriber(
      $service,
      PostGenerateResponseEvent::EVENT_NAME,
      $postGenerate,
    );

    $this->assertCount(0, $this->spanStorage);

    $service->destruct();

    $this->assertCount(1, $this->spanStorage);
  }

  /**
   * Tests that spans include input/output when configured.
   *
   * Exercises the structured/summarized payload path that is enabled by the
   * SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD opt-in.
   */
  public function testSpanWithInputOutput() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_INPUT => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_OUTPUT => TRUE,
      SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD => TRUE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $inputExpectedString = AiObservabilityUtils::aiInputToString(AiObservabilityTestHelper::getInputStub());
    $this->assertEquals($span->getAttributes()->get('input'), $inputExpectedString);
    $outputExpected = AiObservabilityTestHelper::getOutputStub();
    $outputExpectedString = AiObservabilityUtils::aiOutputToString($outputExpected);
    $this->assertEquals($span->getAttributes()->get('output'), $outputExpectedString);
  }

  /**
   * Tests the legacy payload path used when summarization is disabled.
   */
  public function testSpanWithInputOutputLegacy() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_SPANS => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_INPUT => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_STORE_OUTPUT => TRUE,
      SettingsForm::CONFIG_KEY_SUMMARIZE_PAYLOAD => FALSE,
    ]);

    $otelService = TestHelpers::service('opentelemetry', OpentelemetryService::class);
    $tracer = AiObservabilityTestHelper::initOtelSpanStorageStub($this->spanStorage);
    $otelService->method('getTracer')->willReturn($tracer);

    $service = $this->initAiOtelSpansEventSubscriberService();

    $event = AiObservabilityTestHelper::getAiEventStub(PreGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PreGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(1, $this->spanStorage);
    $span = $this->spanStorage[0];
    $this->assertSame(
      AiObservabilityTestHelper::getInputStub()->toString(),
      $span->getAttributes()->get('input'),
    );
    $this->assertSame(
      AiObservabilityUtils::aiOutputToString(AiObservabilityTestHelper::getOutputStub(), FALSE),
      $span->getAttributes()->get('output'),
    );
  }

  /**
   * Initializes the AI OTEL spans event subscriber service for testing.
   *
   * @return \Drupal\ai_observability\EventSubscriber\AiOtelSpansEventSubscriber
   *   The initialized AI OTEL spans event subscriber service.
   */
  private function initAiOtelSpansEventSubscriberService() {
    TestHelpers::service('logger.factory');
    return new AiOtelSpansEventSubscriber(
      TestHelpers::service('config.factory'),
      fn () => TestHelpers::service('logger.factory')->get('ai_observability'),
      TestHelpers::service('opentelemetry'),
    );
  }

}
