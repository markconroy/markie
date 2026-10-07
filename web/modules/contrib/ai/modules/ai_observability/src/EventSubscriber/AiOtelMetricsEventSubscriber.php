<?php

namespace Drupal\ai_observability\EventSubscriber;

use Drupal\ai\Event\AiProviderResponseBaseEvent;
use Drupal\ai\Event\PostGenerateResponseEvent;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai_observability\Form\SettingsForm;
use Drupal\ai_observability\GenAiAttributeMapper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\opentelemetry_metrics\OpentelemetryMetrics;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for AI observability OpenTelemetry metrics export.
 *
 * @package Drupal\ai_observability\EventSubscriber
 */
class AiOtelMetricsEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an AiOtelMetricsEventSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\opentelemetry_metrics\OpentelemetryMetrics $otelMetrics
   *   The OpenTelemetry metrics instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'opentelemetry.metrics')]
    protected OpentelemetryMetrics $otelMetrics,
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PostGenerateResponseEvent::EVENT_NAME => 'otelExportMetrics',
      PostStreamingResponseEvent::EVENT_NAME => 'otelExportMetrics',
    ];
  }

  /**
   * Exports AI events as OpenTelemetry metrics.
   *
   * @param \Drupal\ai\Event\AiProviderResponseBaseEvent $event
   *   The event to export.
   */
  public function otelExportMetrics(AiProviderResponseBaseEvent $event) {
    $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);
    if (
      !$config->get(SettingsForm::CONFIG_KEY_OTEL_ENABLED)
      || !$config->get(SettingsForm::CONFIG_KEY_OTEL_METRICS)
    ) {
      return;
    }

    $output = $event->getOutput();
    if (!method_exists($output, 'getTokenUsage')) {
      return;
    }

    $meter = $this->otelMetrics->getMeter(SettingsForm::OTEL_METER_NAME_TOKEN_USAGE);

    $tokenUsage = $output?->getTokenUsage();
    foreach ($tokenUsage as $key => $value) {
      if ($value === NULL) {
        continue;
      }
      // Prometheus metric names must match [a-zA-Z_:][a-zA-Z0-9_:]*.
      // Use underscores instead of dots and append the token key.
      $metric_name = SettingsForm::OTEL_METRIC_TOKEN_USAGE_PREFIX . '_' . $key;
      $counter = $meter->createCounter($metric_name);
      $counter->add($value, [
        'uid' => $this->currentUser->id(),
        'provider' => $event->getProviderId(),
        'operation_type' => $event->getOperationType(),
        'model' => $event->getModelId(),
      ]);
    }

    // Emit the OTel GenAI semantic-convention histogram for input/output.
    // Only 'input' and 'output' are permitted gen_ai.token.type values for
    // this metric per the OTel GenAI spec; other usage keys (total,
    // reasoning, cached) are omitted deliberately.
    $histogram = $meter->createHistogram(SettingsForm::OTEL_HISTOGRAM_NAME_TOKEN_USAGE);
    $genAiDimensions = [
      'gen_ai.provider.name' => GenAiAttributeMapper::mapProvider($event->getProviderId()),
      'gen_ai.request.model' => $event->getModelId(),
    ];
    foreach (['input' => $tokenUsage->input, 'output' => $tokenUsage->output] as $type => $value) {
      if ($value === NULL) {
        continue;
      }
      $histogram->record($value, ['gen_ai.token.type' => $type] + $genAiDimensions);
    }
  }

}
