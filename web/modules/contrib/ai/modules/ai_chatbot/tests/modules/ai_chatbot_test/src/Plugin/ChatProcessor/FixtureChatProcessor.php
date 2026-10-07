<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot_test\Plugin\ChatProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A minimal ChatProcessor used as a test fixture for AI Chatbot tests.
 */
#[ChatProcessor(
  id: 'chatbot_test_processor',
  label: new TranslatableMarkup('Chatbot Test Processor'),
  description: new TranslatableMarkup('A minimal chat processor used only for testing the DeepChat block.'),
)]
class FixtureChatProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'greeting' => 'Hello',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['greeting'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Greeting'),
      '#description' => $this->t('The greeting prepended to the response.'),
      '#default_value' => $this->configuration['greeting'] ?? 'Hello',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(): ChatOutput {
    $input = $this->getInput();
    if (!$input) {
      throw new \InvalidArgumentException('Input must be set before execution.');
    }

    $messages = $input->getMessages();
    $user_message = $messages[array_key_last($messages)] ?? NULL;
    if (empty($user_message)) {
      throw new \InvalidArgumentException('No user message found in input.');
    }

    $greeting = $this->configuration['greeting'] ?? 'Hello';
    $response_message = new ChatMessage('assistant', $greeting . ' ' . $user_message->getText());

    return new ChatOutput($response_message, [], [], NULL);
  }

}
