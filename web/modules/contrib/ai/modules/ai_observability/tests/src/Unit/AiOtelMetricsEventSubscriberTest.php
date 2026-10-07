<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_observability\Unit;

use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_observability\EventSubscriber\AiOtelMetricsEventSubscriber;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\opentelemetry_metrics\OpentelemetryMetrics;
use Drupal\test_helpers\TestHelpers;
use Drupal\Tests\UnitTestCase;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;

/**
 * Tests AI OpenTelemetry metrics event subscriber.
 *
 * @group ai_observability
 */
class AiOtelMetricsEventSubscriberTest extends UnitTestCase {

  /**
   * Tests that no metrics are exported when OTEL is disabled.
   */
  public function testWithOtelDisabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => FALSE,
    ]);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->never())->method('getMeter');

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);
  }

  /**
   * Tests that metrics are exported when OTEL metrics is enabled.
   */
  public function testWithOtelMetricsEnabled() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_METRICS => TRUE,
    ]);

    $counter = $this->createMock(CounterInterface::class);
    $counter->expects($this->exactly(6))
      ->method('add')
      ->with(
        $this->anything(),
        $this->callback(function ($attributes) {
          return isset($attributes['provider'])
            && isset($attributes['operation_type'])
            && isset($attributes['model']);
        })
      );

    $histogram = $this->createMock(HistogramInterface::class);
    $histogram->expects($this->any())->method('record');

    $meter = $this->createMock(MeterInterface::class);
    $meter->expects($this->exactly(6))
      ->method('createCounter')
      ->willReturn($counter);
    $meter->expects($this->any())
      ->method('createHistogram')
      ->willReturn($histogram);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->atLeastOnce())
      ->method('getMeter')
      ->with(SettingsForm::OTEL_METER_NAME_TOKEN_USAGE)
      ->willReturn($meter);

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $event = AiObservabilityTestHelper::getAiEventStub(PostStreamingResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);
  }

  /**
   * Tests that the GenAI histogram is emitted with the correct dimensions.
   *
   * Fires a single PostGenerateResponseEvent (input:10, output:20, total:30)
   * and asserts that gen_ai.client.token.usage is recorded exactly twice
   * (once for input, once for output) with the correct OTel GenAI attributes
   * AND the correct recorded values (10 for input, 20 for output).
   */
  public function testGenAiTokenUsageHistogram() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_METRICS => TRUE,
    ]);

    $counter = $this->createMock(CounterInterface::class);
    $counter->expects($this->any())->method('add');

    // Capture (value, attributes) pairs to assert exact recorded values.
    $recordedCalls = [];
    $histogram = $this->createMock(HistogramInterface::class);
    $histogram->expects($this->exactly(2))
      ->method('record')
      ->willReturnCallback(function ($value, $attributes) use (&$recordedCalls) {
        $recordedCalls[] = ['value' => $value, 'attributes' => $attributes];
      });

    $meter = $this->createMock(MeterInterface::class);
    $meter->expects($this->any())
      ->method('createCounter')
      ->willReturn($counter);
    $meter->expects($this->once())
      ->method('createHistogram')
      ->with(SettingsForm::OTEL_HISTOGRAM_NAME_TOKEN_USAGE)
      ->willReturn($histogram);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->atLeastOnce())
      ->method('getMeter')
      ->with(SettingsForm::OTEL_METER_NAME_TOKEN_USAGE)
      ->willReturn($meter);

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    // Stub output has TokenUsageDto(input:10, output:20, total:30).
    $event = AiObservabilityTestHelper::getAiEventStub(PostGenerateResponseEvent::class);
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(2, $recordedCalls);

    // Build a map of token type -> recorded value for easier assertions.
    $valueByType = [];
    foreach ($recordedCalls as $call) {
      $this->assertArrayHasKey('gen_ai.token.type', $call['attributes']);
      $this->assertArrayHasKey('gen_ai.provider.name', $call['attributes']);
      $this->assertArrayHasKey('gen_ai.request.model', $call['attributes']);
      $this->assertSame('test-provider', $call['attributes']['gen_ai.provider.name']);
      $this->assertSame('test-model', $call['attributes']['gen_ai.request.model']);
      $valueByType[$call['attributes']['gen_ai.token.type']] = $call['value'];
    }

    $this->assertArrayHasKey('input', $valueByType, 'Input token type must be recorded.');
    $this->assertArrayHasKey('output', $valueByType, 'Output token type must be recorded.');
    $this->assertSame(10, $valueByType['input'], 'Input histogram value must match TokenUsageDto input.');
    $this->assertSame(20, $valueByType['output'], 'Output histogram value must match TokenUsageDto output.');
  }

  /**
   * Tests that histogram record() is called for explicit zero values.
   *
   * Proves the implementation uses a strict NULL check (=== NULL) rather than a
   * truthiness check, so that zero token counts are preserved and emitted.
   */
  public function testGenAiHistogramPreservesZero() {
    TestHelpers::service('config.factory')->stubSetConfig(SettingsForm::CONFIG_NAME, [
      SettingsForm::CONFIG_KEY_OTEL_ENABLED => TRUE,
      SettingsForm::CONFIG_KEY_OTEL_METRICS => TRUE,
    ]);

    $counter = $this->createMock(CounterInterface::class);
    $counter->expects($this->any())->method('add');

    $recordedCalls = [];
    $histogram = $this->createMock(HistogramInterface::class);
    $histogram->expects($this->exactly(2))
      ->method('record')
      ->willReturnCallback(function ($value, $attributes) use (&$recordedCalls) {
        $recordedCalls[] = ['value' => $value, 'attributes' => $attributes];
      });

    $meter = $this->createMock(MeterInterface::class);
    $meter->expects($this->any())
      ->method('createCounter')
      ->willReturn($counter);
    $meter->expects($this->once())
      ->method('createHistogram')
      ->with(SettingsForm::OTEL_HISTOGRAM_NAME_TOKEN_USAGE)
      ->willReturn($histogram);

    $otelMetrics = $this->createMock(OpentelemetryMetrics::class);
    $otelMetrics->expects($this->atLeastOnce())
      ->method('getMeter')
      ->with(SettingsForm::OTEL_METER_NAME_TOKEN_USAGE)
      ->willReturn($meter);

    $service = $this->initAiOtelMetricsEventSubscriberService($otelMetrics);

    $event = new PostGenerateResponseEvent(
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
    TestHelpers::callEventSubscriber($service, PostGenerateResponseEvent::EVENT_NAME, $event);

    $this->assertCount(2, $recordedCalls, 'Histogram must record both tokens even when value is zero.');

    $valueByType = [];
    foreach ($recordedCalls as $call) {
      $valueByType[$call['attributes']['gen_ai.token.type']] = $call['value'];
    }

    $this->assertArrayHasKey('input', $valueByType, 'Input token type must be recorded for zero value.');
    $this->assertArrayHasKey('output', $valueByType, 'Output token type must be recorded for zero value.');
    $this->assertSame(0, $valueByType['input'], 'Explicit zero input must be preserved.');
    $this->assertSame(0, $valueByType['output'], 'Explicit zero output must be preserved.');
  }

  /**
   * Tests that the subscriber is registered for the expected event names.
   *
   * Ensures both PostGenerateResponseEvent and PostStreamingResponseEvent are
   * wired up so that the terminal streaming event triggers metric export.
   */
  public function testSubscribesToTerminalStreamingEvent() {
    $events = AiOtelMetricsEventSubscriber::getSubscribedEvents();

    $this->assertArrayHasKey(
      PostGenerateResponseEvent::EVENT_NAME,
      $events,
      'PostGenerateResponseEvent must be subscribed.'
    );
    $this->assertArrayHasKey(
      PostStreamingResponseEvent::EVENT_NAME,
      $events,
      'PostStreamingResponseEvent must be subscribed.'
    );
  }

  /**
   * Initializes the AI OTEL metrics event subscriber service for testing.
   *
   * @param \Drupal\opentelemetry_metrics\OpentelemetryMetrics $otelMetrics
   *   The OpenTelemetry metrics service mock.
   *
   * @return \Drupal\ai_observability\EventSubscriber\AiOtelMetricsEventSubscriber
   *   The initialized AI OTEL metrics event subscriber service.
   */
  private function initAiOtelMetricsEventSubscriberService($otelMetrics) {
    return new AiOtelMetricsEventSubscriber(
      TestHelpers::service('config.factory'),
      $otelMetrics,
      TestHelpers::service('current_user'),
    );
  }

}
