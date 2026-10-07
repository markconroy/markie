<?php

namespace Drupal\ai_observability\EventSubscriber;

use Drupal\ai\Event\AiProviderRequestBaseEvent;
use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai_observability\AiObservabilityUtils;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\ai_observability\GenAiAttributeMapper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DestructableInterface;
use Drupal\opentelemetry\OpentelemetryService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for AI observability OpenTelemetry spans export.
 *
 * @package Drupal\ai_observability\EventSubscriber
 */
class AiOtelSpansEventSubscriber implements
  EventSubscriberInterface,
  DestructableInterface {

  /**
   * List of processing OTEL spans.
   *
   * @var array<string, \OpenTelemetry\API\Trace\Span>
   */
  protected array $otelSpans = [];

  /**
   * The OpenTelemetry tracer instance.
   *
   * @var \OpenTelemetry\API\Trace\TracerInterface|null
   */
  protected ?TracerInterface $otelTracer = NULL;

  /**
   * Constructs an AiOtelSpansEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Closure<\Psr\Log\LoggerInterface> $loggerClosure
   *   The logger closure.
   * @param \Drupal\opentelemetry\OpentelemetryService $opentelemetry
   *   The OpenTelemetry service.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[AutowireServiceClosure('logger.channel.ai_observability')]
    protected \Closure $loggerClosure,
    #[Autowire(service: 'opentelemetry')]
    protected OpentelemetryService $opentelemetry,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PreGenerateResponseEvent::EVENT_NAME => 'handlePreGenerateResponseEvent',
      PostGenerateResponseEvent::EVENT_NAME => 'handlePostGenerateResponseEvent',
      PostStreamingResponseEvent::EVENT_NAME => 'handlePostGenerateResponseEvent',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    // End any spans left open because their stream never dispatched the
    // terminal PostStreamingResponseEvent, so they are exported rather than
    // leaked.
    foreach ($this->otelSpans as $span) {
      $span->end();
    }
    $this->otelSpans = [];
  }

  /**
   * Handles PreGenerateResponseEvent: starts and stores a span.
   *
   * @param \Drupal\ai\Event\PreGenerateResponseEvent $event
   *   The event to export.
   */
  public function handlePreGenerateResponseEvent(PreGenerateResponseEvent $event): void {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (
      !$config->get(SettingsForm::CONFIG_KEY_OTEL_ENABLED)
      || !$config->get(SettingsForm::CONFIG_KEY_OTEL_SPANS)
      // Exit if no tracer is available.
      || !$this->otelTracer ??= $this->opentelemetry->getTracer()
    ) {
      return;
    }

    $requestId = $event->getRequestThreadId();
    $span = $this->otelTracer->spanBuilder(SettingsForm::OTEL_SPAN_NAME_REQUEST)->startSpan();
    $this->fillRequestSpanAttributes($span, $event);
    $this->otelSpans[$requestId] = $span;
  }

  /**
   * Handles Post*ResponseEvent events: ends span.
   *
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to export.
   */
  public function handlePostGenerateResponseEvent(AiProviderRequestBaseEvent $event): void {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (
      !$config->get(SettingsForm::CONFIG_KEY_OTEL_ENABLED)
      || !$config->get(SettingsForm::CONFIG_KEY_OTEL_SPANS)
      // Exit if no tracer is available.
      || !$this->otelTracer ??= $this->opentelemetry->getTracer()
    ) {
      return;
    }

    $requestId = $event->getRequestThreadId();
    if (!isset($this->otelSpans[$requestId])) {
      // No span found for this request ID.
      return;
    }
    $span = $this->otelSpans[$requestId];

    // Streaming fires two events here. The initial PostGenerateResponseEvent
    // carries the un-consumed iterator (empty usage); defer finalization to the
    // terminal event, which carries the consumed token usage. A stream iterator
    // that never dispatches the terminal event would keep an unfinished span.
    $output = $event->getOutput();
    $normalized = method_exists($output, 'getNormalized')
      ? $output->getNormalized()
      : NULL;
    if ($normalized instanceof StreamedChatMessageIteratorInterface
      && !$event instanceof PostStreamingResponseEvent) {
      return;
    }

    $this->fillResponseSpanAttributes($span, $event);
    $span->end();
    unset($this->otelSpans[$requestId]);
  }

  /**
   * Fills span attributes for the request event.
   *
   * @param \OpenTelemetry\API\Trace\Span $span
   *   The span to fill.
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent $event
   *   The event to get data from.
   */
  protected function fillRequestSpanAttributes(Span $span, AiProviderRequestBaseEvent $event): void {
    // @todo Add settings to choose which fields to submit.
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    $span->setAttribute('provider', $event->getProviderId());
    $span->setAttribute('operation_type', $event->getOperationType());
    $span->setAttribute('model', $event->getModelId());
    $span->setAttribute('provider_request_id', $event->getRequestThreadId());
    $span->setAttribute('provider_request_parent_id', $event->getRequestParentId());
    $span->setAttribute('configuration', Json::encode($event->getConfiguration()));
    $span->setAttribute('tags', $event->getTags());

    // GenAI semantic-convention (gen_ai.*) attributes, additive.
    $span->setAttribute('gen_ai.provider.name', GenAiAttributeMapper::mapProvider($event->getProviderId()));
    $operationName = GenAiAttributeMapper::mapOperation($event->getOperationType());
    if ($operationName !== NULL) {
      $span->setAttribute('gen_ai.operation.name', $operationName);
    }
    $span->setAttribute('gen_ai.request.model', $event->getModelId());

    // Optionally submit input to the span if enabled in configuration.
    if ($config->get(SettingsForm::CONFIG_KEY_OTEL_STORE_INPUT)) {
      $payload = $event->getInput();
      // @todo Remove this check when https://www.drupal.org/i/3567673 is fixed.
      if ($payload instanceof InputInterface) {
        $summarizeOptions = AiObservabilityUtils::buildSummarizeOptions($config);
        $span->setAttribute('input', AiObservabilityUtils::summarizeAiPayloadData(
          AiObservabilityUtils::aiInputToString($payload, $summarizeOptions['summarize']),
          1024,
          $summarizeOptions,
        ));
      }
      else {
        $span->setAttribute('input', 'Unsupported input type: ' . get_debug_type($payload));
      }
    }
  }

  /**
   * Fills span attributes for the response event.
   *
   * @param \OpenTelemetry\API\Trace\Span $span
   *   The span to fill.
   * @param \Drupal\ai\Event\AiProviderRequestBaseEvent|\Drupal\ai\Event\AiProviderResponseBaseEvent $event
   *   The event to get data from.
   */
  protected function fillResponseSpanAttributes(Span $span, AiProviderResponseBaseEvent $event): void {
    $output = $event->getOutput();
    if ($output !== NULL && method_exists($output, 'getTokenUsage')) {
      $tokenUsage = $output->getTokenUsage()->toArray();
      // Remove NULL values because OpenTelemetry attributes don't support them.
      $tokenUsage = array_filter($tokenUsage);
      $span->setAttribute('token_usage', $tokenUsage);

      // GenAI semantic-convention scalar token usage. Preserves explicit 0.
      $usage = $output->getTokenUsage();
      if ($usage->input !== NULL) {
        $span->setAttribute('gen_ai.usage.input_tokens', $usage->input);
      }
      if ($usage->output !== NULL) {
        $span->setAttribute('gen_ai.usage.output_tokens', $usage->output);
      }
    }

    // The gen_ai.response.model from raw output when available.
    if ($output !== NULL && method_exists($output, 'getRawOutput')) {
      $raw = $output->getRawOutput();
      if (is_array($raw) && !empty($raw['model'])) {
        $span->setAttribute('gen_ai.response.model', $raw['model']);
      }
    }

    // The gen_ai.response.finish_reasons from the raw output.
    $finishReasons = $this->extractFinishReasons($event);
    if (!empty($finishReasons)) {
      $span->setAttribute('gen_ai.response.finish_reasons', $finishReasons);
    }

    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

    if ($config->get(SettingsForm::CONFIG_KEY_OTEL_STORE_OUTPUT)) {
      $payload = $event->getOutput();
      $summarizeOptions = AiObservabilityUtils::buildSummarizeOptions($config);
      $payloadStringified = AiObservabilityUtils::aiOutputToString($payload, $summarizeOptions['summarize']);
      $span->setAttribute('output', AiObservabilityUtils::summarizeAiPayloadData(
        $payloadStringified,
        1024,
        $summarizeOptions,
      ));
    }
  }

  /**
   * Extracts finish reasons from a response event.
   *
   * Inspects the raw output array for known provider shapes:
   * - OpenAI: one finish_reason per choice across all $raw['choices'] entries.
   * - Anthropic: $raw['stop_reason'] as the single stop reason.
   *
   * Returns an empty array when no finish reason can be determined.
   *
   * @param \Drupal\ai\Event\AiProviderResponseBaseEvent $event
   *   The response event whose output is inspected.
   *
   * @return string[]
   *   An array of finish reason strings, or an empty array when none found.
   */
  private function extractFinishReasons(AiProviderResponseBaseEvent $event): array {
    $output = $event->getOutput();
    if ($output === NULL || !method_exists($output, 'getRawOutput')) {
      return [];
    }

    $raw = $output->getRawOutput();
    if (!is_array($raw)) {
      return [];
    }

    // OpenAI shape: one finish_reason per choice.
    if (!empty($raw['choices']) && is_array($raw['choices'])) {
      $reasons = array_values(array_filter(
        array_column($raw['choices'], 'finish_reason'),
        static fn ($reason) => $reason !== NULL && $reason !== '',
      ));
      if ($reasons) {
        return $reasons;
      }
    }

    if (isset($raw['stop_reason']) && $raw['stop_reason'] !== '') {
      return [$raw['stop_reason']];
    }

    return [];
  }

}
