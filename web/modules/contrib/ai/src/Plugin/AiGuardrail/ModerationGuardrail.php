<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\Guardrail\UserMessageSelectionTrait;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\Moderation\ModerationInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the Moderation guardrail.
 *
 * Sends user chat messages to a configurable moderation provider/model and
 * stops the request if the moderation endpoint flags the content. By default
 * only the most recent user message is checked; the "scan all user messages"
 * option extends the check to every user turn in the conversation. This adapts
 * the global moderation behavior of ModeratePreRequestEventSubscriber into a
 * per-guardrail-set check.
 *
 * The provider and model are selected together as a single "provider__model"
 * value, mirroring how the rest of the module configures moderation (see
 * \Drupal\ai\Form\ModerationConfigurations). Moderation has no per-model
 * configuration to collect, so the chat-oriented provider form is intentionally
 * not used here.
 */
#[AiGuardrail(
  id: 'moderation',
  label: new TranslatableMarkup('Moderation'),
  description: new TranslatableMarkup(
    'Sends the input to a configurable moderation provider/model and blocks it if the content is flagged as harmful or inappropriate.'
  ),
)]
final class ModerationGuardrail extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface, NonDeterministicGuardrailInterface, NonStreamableGuardrailInterface {

  use NeedsAiPluginManagerTrait;
  use StringTranslationTrait;
  use UserMessageSelectionTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'flagged_message' => 'The content was flagged by the moderation endpoint and the request was stopped.',
      'moderation_model' => '',
      'scan_all_user_messages' => FALSE,
    ];
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
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['flagged_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to send if the content is flagged'),
      '#description' => $this->t('Shown to the user when the moderation endpoint flags the input.'),
      '#default_value' => $this->configuration['flagged_message'] ?? $this->defaultConfiguration()['flagged_message'],
    ];

    $form['moderation_model'] = [
      '#type' => 'select',
      '#title' => $this->t('Moderation provider and model'),
      '#description' => $this->t('The moderation provider and model used to evaluate the input. Leave as "- None -" to use the default moderation provider configured for the site.'),
      '#options' => $this->getAiPluginManager()->getSimpleProviderModelOptions('moderation'),
      '#default_value' => $this->configuration['moderation_model'] ?? '',
    ];

    $form['scan_all_user_messages'] = $this->buildScanAllUserMessagesElement(
      (string) $this->t('When enabled, every user message in the chat history is sent to the moderation endpoint and the request is stopped as soon as any one of them is flagged. Useful when conversation history may have been imported, replayed, or scanned under different rules. When disabled (default) only the latest user message is moderated, even if a tool result message is technically more recent.'),
      !empty($this->configuration['scan_all_user_messages']),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration([
      'flagged_message' => $form_state->getValue('flagged_message'),
      'moderation_model' => $form_state->getValue('moderation_model'),
      'scan_all_user_messages' => (bool) $form_state->getValue('scan_all_user_messages'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping moderation.', $this);
    }

    $scan_all = !empty($this->configuration['scan_all_user_messages']);
    $user_messages = $this->selectUserMessages($input, $scan_all);
    if ($user_messages === []) {
      return new PassResult('No user message found to moderate.', $this);
    }

    $selected = $this->configuration['moderation_model'] ?? '';
    if ($selected === '') {
      // Fall back to the site default moderation provider.
      $default = $this->getAiPluginManager()->getDefaultProviderForOperationType('moderation');
      if ($default === NULL) {
        // Fail closed: a moderation guardrail with no usable provider should
        // not let potentially unsafe content through silently.
        return new StopResult('No moderation provider is configured. Please select a moderation provider/model for this guardrail or configure a default moderation provider in the AI module settings.', $this);
      }
      $provider_id = $default['provider_id'];
      $model_id = $default['model_id'];
    }
    else {
      // The selection is stored as "provider__model".
      [$provider_id, $model_id] = array_pad(explode('__', $selected, 2), 2, '');
    }

    $provider = $this->getAiPluginManager()->createInstance($provider_id);

    // createInstance() returns a ProviderProxy that delegates operations to
    // the underlying provider plugin via __call(), so the moderation
    // capability has to be checked on the wrapped plugin rather than on the
    // proxy. A provider that does not support moderation (for example a stale
    // stored selection) would otherwise fatal on the moderation() call below,
    // so fail closed instead of letting the content through.
    if (!$provider->getPlugin() instanceof ModerationInterface) {
      return new StopResult(
        sprintf('The configured provider "%s" does not support moderation. Please select a moderation-capable provider/model for this guardrail.', $provider_id),
        $this,
      );
    }

    // Moderate each selected user message and stop on the first flag. When
    // scanning is limited to the latest message this loop runs once; when
    // "scan all user messages" is enabled every user turn is checked so
    // harmful content earlier in the conversation cannot slip through.
    foreach ($user_messages as $message) {
      $text = $message->getText();
      if ($text === '') {
        continue;
      }

      // @phpstan-ignore-next-line moderation() is proxied through __call().
      $moderation = $provider->moderation($text, $model_id, ['ai'])->getNormalized();
      if ($moderation->isFlagged()) {
        return new StopResult(
          $this->configuration['flagged_message'] ?? $this->defaultConfiguration()['flagged_message'],
          $this,
          ['moderation_information' => $moderation->getInformation()],
        );
      }
    }

    return new PassResult('The content passed moderation.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    // This guardrail only moderates input, not output.
    return new PassResult('Output processing is not applicable for this guardrail.', $this);
  }

}
