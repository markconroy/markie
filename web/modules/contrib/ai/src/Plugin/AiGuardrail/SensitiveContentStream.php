<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\RewriteOutputResult;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Streaming guardrail that suppresses content between configurable markers.
 *
 * Content flowing through the stream is passed through unchanged until the
 * start marker is detected. From that point onward the iterator buffers all
 * chunks until the stop marker appears. The entire buffered span (markers
 * included) is then replaced with the configured replacement message so the
 * sensitive segment never reaches the consumer.
 */
#[AiGuardrail(
  id: 'sensitive_content_stream',
  label: new TranslatableMarkup('Sensitive Content Stream Filter'),
  description: new TranslatableMarkup(
    'Suppresses content enclosed between configurable start/stop markers during streaming and replaces it with a safe message.'
  ),
)]
class SensitiveContentStream extends AiGuardrailPluginBase implements StreamableGuardrailInterface, ConfigurableInterface, PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getStartRegex(): string {
    $marker = $this->configuration['start_marker'] ?? '[SENSITIVE]';
    if (empty($marker)) {
      return '';
    }
    return '/' . preg_quote($marker, '/') . '/';
  }

  /**
   * {@inheritdoc}
   */
  public function getStopRegex(): string {
    $marker = $this->configuration['stop_marker'] ?? '[/SENSITIVE]';
    if (empty($marker)) {
      return '';
    }
    return '/' . preg_quote($marker, '/') . '/';
  }

  /**
   * {@inheritdoc}
   */
  public function processStreamedBuffer(string $buffered_content): GuardrailResultInterface {
    $replacement = $this->configuration['replacement_message'] ?? '[Content removed.]';
    return new RewriteOutputResult($replacement, $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    return new PassResult('Streaming guardrail does not process input.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('Streaming guardrail processes chunks via processStreamedBuffer().', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'start_marker' => '[SENSITIVE]',
      'stop_marker' => '[/SENSITIVE]',
      'replacement_message' => '[Content removed.]',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['start_marker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Start marker'),
      '#description' => $this->t('Plain text marker that signals the start of sensitive content in the stream (e.g. <code>[SENSITIVE]</code>).'),
      '#default_value' => $this->configuration['start_marker'] ?? '[SENSITIVE]',
      '#required' => TRUE,
    ];

    $form['stop_marker'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stop marker'),
      '#description' => $this->t('Plain text marker that signals the end of sensitive content in the stream (e.g. <code>[/SENSITIVE]</code>).'),
      '#default_value' => $this->configuration['stop_marker'] ?? '[/SENSITIVE]',
      '#required' => TRUE,
    ];

    $form['replacement_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Replacement message'),
      '#description' => $this->t('Text shown in place of the suppressed content.'),
      '#default_value' => $this->configuration['replacement_message'] ?? '[Content removed.]',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $start = $form_state->getValue('start_marker');
    $stop = $form_state->getValue('stop_marker');
    if (!empty($start) && $start === $stop) {
      $form_state->setErrorByName('stop_marker', $this->t('The stop marker must differ from the start marker.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['start_marker'] = $form_state->getValue('start_marker');
    $this->configuration['stop_marker'] = $form_state->getValue('stop_marker');
    $this->configuration['replacement_message'] = $form_state->getValue('replacement_message');
  }

}
