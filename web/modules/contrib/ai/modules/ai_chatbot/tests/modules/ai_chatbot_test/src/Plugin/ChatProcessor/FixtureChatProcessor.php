<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot_test\Plugin\ChatProcessor;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai_chatbot\Service\MessagesButtons;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

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
   * The messages buttons service.
   *
   * @var \Drupal\ai_chatbot\Service\MessagesButtons
   */
  protected MessagesButtons $messagesButtons;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->messagesButtons = $container->get('ai_chatbot.buttons');
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'greeting' => 'Hello',
      'show_structured_results' => FALSE,
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

    $form['show_structured_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Structured Results'),
      '#description' => $this->t('Append fixed structured results to the response.'),
      '#default_value' => $this->configuration['show_structured_results'] ?? FALSE,
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

  /**
   * {@inheritdoc}
   *
   * Mirrors the structured results markup contract of
   * \Drupal\ai_chatbot\Plugin\ChatProcessor\AiAssistantApiProcessor with a
   * fixed payload, so tests can exercise the structured results modal.
   */
  public function getPostResponseMarkup(): string {
    if (empty($this->configuration['show_structured_results'])) {
      return '';
    }
    $structured = [
      'fixture' => [
        'status' => 'ok',
        'items' => ['first', 'second'],
      ],
    ];
    $buttons = [
      [
        'svg' => $this->moduleHandler->getModule('ai_chatbot')->getPath() . '/assets/combine-left-right-icon.svg',
        'weight' => 1,
        'class' => ['structured-results'],
        'alt' => $this->t('Structured results'),
        'title' => $this->t('Structured results'),
      ],
    ];
    $markup = $this->messagesButtons->getRenderedButtons($buttons, '', $this->getThreadId() ?? '');
    $markup .= '<div class="structured-results-dump"><pre>' . Html::escape(Yaml::dump($structured, 10)) . '</pre></div>';
    return $markup;
  }

}
