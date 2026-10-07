<?php

namespace Drupal\ai_chatbot\Plugin\ChatProcessor;

use Drupal\ai\Exception\AiBadRequestException;
use Drupal\ai\Exception\AiQuotaException;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\Exception\AiRequestErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai_assistant_api\AiAssistantApiRunner;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_chatbot\Service\MessagesButtons;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * AI Assistant API ChatProcessor plugin.
 *
 * This plugin integrates with the existing AI Assistant API to provide
 * backward compatibility and leverage existing assistant configurations.
 */
#[ChatProcessor(
  id: 'ai_assistant_api_processor',
  label: new TranslatableMarkup('AI Assistant API Processor'),
  description: new TranslatableMarkup('Integrates with the existing AI Assistant API for backward compatibility.')
)]
class AiAssistantApiProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The AI Assistant API runner.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantApiRunner
   */
  protected AiAssistantApiRunner $aiAssistantRunner;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The messages buttons render service.
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
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai_assistant_api.runner'),
      $container->get('entity_type.manager'),
      $container->get('ai_chatbot.buttons'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AiAssistantApiRunner $aiAssistantRunner, EntityTypeManagerInterface $entityTypeManager, MessagesButtons $messagesButtons, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aiAssistantRunner = $aiAssistantRunner;
    $this->entityTypeManager = $entityTypeManager;
    $this->messagesButtons = $messagesButtons;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'assistant_id' => '',
      'stream_output' => FALSE,
      'verbose_mode' => FALSE,
      'show_structured_results' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    // Get available AI Assistants.
    $assistantStorage = $this->entityTypeManager->getStorage('ai_assistant');
    $assistants = $assistantStorage->loadMultiple();

    $options = [];
    foreach ($assistants as $assistant) {
      $options[$assistant->id()] = $assistant->label();
    }

    $form['assistant_id'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Assistant'),
      '#description' => $this->t('Select the AI Assistant to use for processing.'),
      '#options' => $options,
      '#default_value' => $this->configuration['assistant_id'],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select an Assistant -'),
    ];

    $form['stream_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Stream Output'),
      '#description' => $this->t('Enable streaming output for real-time responses.'),
      '#default_value' => $this->configuration['stream_output'],
    ];

    $form['verbose_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Verbose Mode'),
      '#description' => $this->t('Enable verbose mode for detailed logging and debugging.'),
      '#default_value' => $this->configuration['verbose_mode'],
    ];

    $form['show_structured_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Structured Results'),
      '#description' => $this->t('Append the structured results from assistant actions to the response.'),
      '#default_value' => $this->configuration['show_structured_results'],
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

    $this->initializeRunner();
    $this->aiAssistantRunner->streamedOutput($this->configuration['stream_output'] ?? FALSE);

    if ($this->configuration['verbose_mode'] ?? FALSE) {
      $this->aiAssistantRunner->setVerboseMode(TRUE);
    }
    $contexts = $input->getRequestMetadataValue('contexts');
    if (!empty($contexts)) {
      $this->aiAssistantRunner->setContext($contexts);
    }

    // Convert ChatInput to UserMessage for the AI Assistant API.
    $userMessage = $this->convertInputToUserMessage($input);
    $this->aiAssistantRunner->setUserMessage($userMessage);
    try {
      // The response is already a ChatOutput object from the AI Assistant API.
      $output = $this->aiAssistantRunner->process();
    }
    catch (\Exception $e) {
      $assistant = $this->aiAssistantRunner->getAssistant();
      $error_message = $assistant->get('error_message');
      // Try to find a specific error message to use.
      if ($e instanceof AiBadRequestException) {
        $error_message = $assistant->get('specific_error_messages')['AiBadRequestException'] ?? $error_message;
      }
      elseif ($e instanceof AiRateLimitException) {
        $error_message = $assistant->get('specific_error_messages')['AiRateLimitException'] ?? $error_message;
      }
      elseif ($e instanceof AiQuotaException) {
        $error_message = $assistant->get('specific_error_messages')['AiQuotaException'] ?? $error_message;
      }
      elseif ($e instanceof AiSetupFailureException) {
        $error_message = $assistant->get('specific_error_messages')['AiSetupFailureException'] ?? $error_message;
      }
      elseif ($e instanceof AiRequestErrorException) {
        $error_message = $assistant->get('specific_error_messages')['AiRequestErrorException'] ?? $error_message;
      }
      // Re-throw the message.
      throw new AiRequestErrorException($error_message);
    }

    // The runner persists the user message itself, but the assistant reply
    // must be stored by the caller once it is complete, so the next request
    // on this thread sees the full history. Streamed replies are handed
    // back via onStreamComplete() instead. The structured results are NOT
    // appended here: the consumer fetches them via getPostResponseMarkup()
    // after sanitizing the model output.
    $normalized = $output->getNormalized();
    if ($normalized instanceof ChatMessage) {
      $this->aiAssistantRunner->setAssistantMessage($normalized->getText());
    }

    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $assistant = $this->loadAssistant();
    if (empty($assistant)) {
      // Misconfigured or deleted assistant: nobody gets through, but a new
      // assistant appearing must invalidate this.
      return AccessResult::forbidden('The configured AI Assistant does not exist.')
        ->addCacheTags(['ai_assistant_list']);
    }
    // Check if the assistant is enabled.
    if (!$assistant->status()) {
      return AccessResult::forbidden()->addCacheableDependency($assistant);
    }

    // Set the assistant in the runner and check setup and access.
    $this->aiAssistantRunner->setAssistant($assistant);
    if (!$this->aiAssistantRunner->isSetup()) {
      return AccessResult::forbidden()->addCacheableDependency($assistant);
    }
    if (!$this->aiAssistantRunner->userHasAccess()) {
      return AccessResult::forbidden()->addCacheableDependency($assistant)->cachePerUser();
    }

    // If all checks pass, allow access.
    return AccessResult::allowed()->addCacheableDependency($assistant)->cachePerUser();
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageHistory(): array {
    $thread_id = $this->getThreadId();
    if (!$thread_id) {
      return [];
    }
    $this->initializeRunner();
    $this->aiAssistantRunner->setThreadsKey($thread_id);
    return $this->aiAssistantRunner->getMessageHistory();
  }

  /**
   * {@inheritdoc}
   */
  public function onStreamComplete(string $message): void {
    if ($message === '') {
      return;
    }
    $this->initializeRunner();
    $this->aiAssistantRunner->setAssistantMessage($message);
  }

  /**
   * {@inheritdoc}
   */
  public function getPostResponseMarkup(): string {
    if (empty($this->configuration['show_structured_results'])) {
      return '';
    }
    $this->initializeRunner();
    $structured = $this->aiAssistantRunner->getStructuredResults();
    if (!$structured) {
      return '';
    }
    // The toggle button opens the (hidden) dump in a modal; see the
    // structured-results handling in deepchat-init.js.
    $buttons = [
      [
        'svg' => $this->moduleHandler->getModule('ai_chatbot')->getPath() . '/assets/combine-left-right-icon.svg',
        'weight' => 1,
        'class' => ['structured-results'],
        'alt' => $this->t('Structured results'),
        'title' => $this->t('Structured results'),
      ],
    ];
    $markup = $this->messagesButtons->getRenderedButtons($buttons, $this->configuration['assistant_id'] ?? '', $this->getThreadId() ?? '');
    // The dump may embed model and user provided content, so escape it.
    $markup .= '<div class="structured-results-dump"><pre>' . Xss::filter(Yaml::dump($structured, 10)) . '</pre></div>';
    return $markup;
  }

  /**
   * Loads the configured AI Assistant entity.
   *
   * @return \Drupal\ai_assistant_api\Entity\AiAssistant|null
   *   The assistant, or NULL when unset or missing.
   */
  protected function loadAssistant(): ?AiAssistant {
    $assistantId = $this->configuration['assistant_id'] ?? '';
    if (empty($assistantId)) {
      return NULL;
    }
    $assistant = $this->entityTypeManager->getStorage('ai_assistant')->load($assistantId);
    return $assistant instanceof AiAssistant ? $assistant : NULL;
  }

  /**
   * Sets the assistant and thread key on the runner.
   *
   * @throws \InvalidArgumentException
   *   When no valid assistant is configured.
   */
  protected function initializeRunner(): void {
    $assistant = $this->loadAssistant();
    if (!$assistant) {
      throw new \InvalidArgumentException('A valid AI Assistant ID must be configured.');
    }
    $this->aiAssistantRunner->setAssistant($assistant);
    // Honor an explicitly set thread id; otherwise the runner owns the key.
    if ($this->threadId) {
      $this->aiAssistantRunner->setThreadsKey($this->threadId);
    }
  }

  /**
   * Converts ChatInput to UserMessage for AI Assistant API.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input.
   *
   * @return \Drupal\ai_assistant_api\Data\UserMessage
   *   The user message.
   */
  protected function convertInputToUserMessage(ChatInput $input): UserMessage {
    $messages = $input->getMessages();
    $userText = '';

    // Extract the latest user message.
    foreach ($messages as $message) {
      if ($message instanceof ChatMessage && $message->getRole() === 'user') {
        $userText = $message->getText();
      }
    }

    return new UserMessage($userText);
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadId(): ?string {
    // An explicitly set thread id (e.g. sent by the client) wins.
    if ($this->threadId) {
      return $this->threadId;
    }
    // Otherwise let the runner own the thread key. It needs the assistant to
    // resolve one, so set it first; without a valid assistant there is no
    // thread.
    $assistant = $this->loadAssistant();
    if (!$assistant) {
      return NULL;
    }
    $this->aiAssistantRunner->setAssistant($assistant);
    return $this->aiAssistantRunner->getThreadsKey();
  }

  /**
   * {@inheritdoc}
   */
  public function resetThread($thread_id): string {
    $assistant = $this->loadAssistant();
    if (!$assistant) {
      return $thread_id;
    }
    $this->aiAssistantRunner->setAssistant($assistant);
    return $this->aiAssistantRunner->resetThread($thread_id);
  }

}
