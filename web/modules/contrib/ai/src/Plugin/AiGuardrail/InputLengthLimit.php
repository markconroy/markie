<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\Guardrail\UserMessageSelectionTrait;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Blocks input that exceeds a configurable length limit.
 *
 * Supports both character-based and token-based counting, and can check
 * just the last user message or the entire conversation.
 */
#[AiGuardrail(
  id: 'input_length_limit',
  label: new TranslatableMarkup('Input Length Limit'),
  description: new TranslatableMarkup('Blocks input that exceeds a configurable character or token count limit.'),
)]
class InputLengthLimit extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use UserMessageSelectionTrait;

  /**
   * Constructs an InputLengthLimit guardrail plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\Utility\TokenizerInterface $tokenizer
   *   The tokenizer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected TokenizerInterface $tokenizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.tokenizer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping.', $this);
    }

    $max_length = (int) ($this->configuration['max_length'] ?? 0);
    if ($max_length <= 0) {
      return new PassResult('No length limit configured, skipping check.', $this);
    }

    $use_tokens = !empty($this->configuration['use_tokens']);

    $scan_all = !empty($this->configuration['scan_all_user_messages']);
    $user_messages = $this->selectUserMessages($input, $scan_all);
    if ($user_messages === []) {
      return new PassResult('No user message found to analyze.', $this);
    }

    $parts = [];
    foreach ($user_messages as $message) {
      $parts[] = $message->getText();
    }
    $text = implode("\n", $parts);

    // Measure length.
    if ($use_tokens) {
      $this->tokenizer->setModel($this->configuration['tokenizer_model'] ?? 'gpt-4');
      $length = $this->tokenizer->countTokens($text);
      $unit = 'tokens';
    }
    else {
      $length = mb_strlen($text);
      $unit = 'characters';
    }

    if ($length > $max_length) {
      $violation_message = $this->configuration['violation_message'] ?? 'Your input has @count @unit, which exceeds the maximum of @max @unit.';
      $violation_message = str_replace(
        ['@count', '@max', '@unit'],
        [(string) $length, (string) $max_length, $unit],
        $violation_message,
      );
      return new StopResult($violation_message, $this);
    }

    return new PassResult('Input length within limits.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('Output processing is not applicable for this guardrail.', $this);
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
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum allowed length. Interpreted as characters or tokens depending on the setting below.'),
      '#default_value' => $this->configuration['max_length'] ?? 5000,
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['use_tokens'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use token-based counting'),
      '#description' => $this->t('When enabled, the limit is applied to the number of tokens instead of characters. Uses the ai.tokenizer service.'),
      '#default_value' => $this->configuration['use_tokens'] ?? FALSE,
    ];

    $form['tokenizer_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tokenizer model'),
      '#description' => $this->t('The model to use for token counting (e.g. gpt-4, gpt-3.5-turbo). Only used when token-based counting is enabled.'),
      '#default_value' => $this->configuration['tokenizer_model'] ?? 'gpt-4',
      '#states' => [
        'visible' => [
          ':input[name="guardrail_settings[use_tokens]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['scan_all_user_messages'] = $this->buildScanAllUserMessagesElement(
      (string) $this->t('When enabled, every user message in the chat history is combined and measured together, not only the most recent one. Useful when conversation history may have been imported, replayed, or scanned under different rules. When disabled (default) only the latest user message is measured, even if a tool result message is technically more recent.'),
      !empty($this->configuration['scan_all_user_messages']),
    );

    $form['violation_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Violation message'),
      '#description' => $this->t('The message displayed when the limit is exceeded. Available placeholders: @count (actual length), @max (configured limit), @unit (characters or tokens).'),
      '#default_value' => $this->configuration['violation_message'] ?? 'Your input has @count @unit, which exceeds the maximum of @max @unit.',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

}
