<?php
// phpcs:ignoreFile
namespace Drupal\ai_assistant_api;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_assistant_api\Data\AssistantStreamIterator;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_assistant_api\Event\AiAssistantSystemRoleEvent;
use Drupal\ai_assistant_api\Event\PrepromptSystemRoleEvent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The runner for the AI assistant.
 */
class AiAssistantApiRunner {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The assistant.
   *
   * @var \Drupal\ai_assistant_api\Entity\AiAssistant|null
   */
  protected AiAssistant|NULL $assistant = NULL;

  /**
   * The AI provider service.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * The message to send to the assistant.
   *
   * @var \Drupal\ai_assistant_api\Data\UserMessage|null
   */
  protected UserMessage|NULL $userMessage;

  /**
   * The Drupal renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected RendererInterface $renderer;

  /**
   * The private temp store.
   *
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected PrivateTempStoreFactory $tempStore;

  /**
   * The AI Assistant Action Plugin Manager.
   *
   * @var \Drupal\ai_assistant_api\AiAssistantActionPluginManager
   */
  protected AiAssistantActionPluginManager $actions;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * Get the current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The title resolver.
   *
   * @var \Drupal\Core\TitleResolverInterface
   */
  protected TitleResolverInterface $titleResolver;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerChannelFactory;

  /**
   * The message to json service.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * If it should be a streaming result.
   *
   * @var bool
   */
  protected bool $streaming = FALSE;

  /**
   * The context for the assistant.
   *
   * @var array
   */
  protected array $context = [];

  /**
   * The history storage for the assistant.
   *
   * @var array
   */
  protected array $history = [];

  /**
   * Boolean to keep track if the context was used.
   *
   * @var bool
   */
  protected bool $contextUsed = FALSE;

  /**
   * Set token replacements.
   *
   * @var array
   */
  protected array $tokens = [];

  /**
   * The thread id to use for history.
   *
   * @var string
   */
  protected string $thread_id = '';

  /**
   * Let the system know if an action is being used.
   *
   * @var bool
   */
  protected bool $using_action = FALSE;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\ai\AiProviderPluginManager $aiProvider
   *   The AI provider service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The Drupal renderer.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStore
   *   The private temp store.
   * @param \Drupal\ai_assistant_api\AiAssistantActionPluginManager $actions
   *   The AI Assistant Action Plugin Manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Controller\TitleResolverInterface $titleResolver
   *   The title resolver.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The message to json service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AiProviderPluginManager $aiProvider,
    Renderer $renderer,
    PrivateTempStoreFactory $tempStore,
    AiAssistantActionPluginManager $actions,
    EventDispatcherInterface $eventDispatcher,
    AccountProxyInterface $currentUser,
    RequestStack $requestStack,
    TitleResolverInterface $titleResolver,
    LanguageManagerInterface $languageManager,
    ConfigFactoryInterface $configFactory,
    LoggerChannelFactoryInterface $loggerChannelFactory,
    PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aiProvider = $aiProvider;
    $this->renderer = $renderer;
    $this->tempStore = $tempStore;
    $this->actions = $actions;
    $this->eventDispatcher = $eventDispatcher;
    $this->currentUser = $currentUser;
    $this->requestStack = $requestStack;
    $this->titleResolver = $titleResolver;
    $this->languageManager = $languageManager;
    $this->configFactory = $configFactory;
    $this->loggerChannelFactory = $loggerChannelFactory;
    $this->promptJsonDecoder = $promptJsonDecoder;
  }

  /**
   * Gets the assistant.
   *
   * @return \Drupal\ai_assistant_api\Entity\AiAssistant
   */
  public function getAssistant() {
    return $this->assistant;
  }

  /**
   * Set the assistant.
   *
   * @param \Drupal\ai_assistant_api\Entity\AiAssistant $assistant
   *   The assistant.
   */
  public function setAssistant(AiAssistant $assistant) {
    $this->assistant = $assistant;

    // Set the thread id.
    if ($this->assistant->get('allow_history') == 'session' && !$this->thread_id) {
      $this->thread_id = $this->generateUniqueKey();
    }
  }

  /**
   * Set the context.
   *
   * @param array $context
   *   The context to set.
   */
  public function setContext($context) {
    $this->context = $context;
  }

  /**
   * Set streaming.
   *
   * @param bool $streaming
   *   If the output should be streamed.
   */
  public function streamedOutput(bool $streaming) {
    $this->streaming = $streaming;
  }

  /**
   * Set a message to the assistant.
   *
   * @param \Drupal\ai_assistant_api\Data\UserMessage $userMessage
   *   The message to set.
   */
  public function setUserMessage(UserMessage $userMessage) {
    $this->userMessage = $userMessage;
    $this->tokens['question'] = $userMessage->getMessage();

    // If session is set, we store the user message.
    if ($this->assistant->get('allow_history') == 'session') {
      $this->addMessageToSession('user', $this->userMessage->getMessage());
    }
  }

  /**
   * Sets an assistant message. Because of streaming this is post render.
   *
   * @param string $message
   *   The message to set.
   */
  public function setAssistantMessage($message) {
    // If session is set, we store the assistant message.
    if ($this->assistant->get('allow_history') == 'session') {
      $this->addMessageToSession('assistant', $message);
    }
  }

  /**
   * Gets a unique storage key for the assistant.
   *
   * @return string
   */
  public function generateUniqueKey($type = 'session') {
    // Iterate over the keys until a new one is found.
    $i = 0;
    while (TRUE) {
      $key = 'assistant_thread_' . $i;
      $thread = $this->getTempStore()->get($key);
      // If its old, we reuse it.
      if (isset($thread['created']) && (time() - $thread['created']) > 86400) {
        return $key;
      }
      // If its over 10, we start removing them from 0.
      if ($i > 10) {
        $this->getTempStore()->delete('assistant_thread_' . ($i - 5));
      }
      // If its not set, we use it.
      if (!$thread) {
        return $key;
      }
      $i++;
    }

  }

  /**
   * Gets the thread id.
   *
   * @return string
   *   The thread id.
   */
  public function getThreadsKey() {
    if (!$this->thread_id) {
      $this->thread_id = $this->generateUniqueKey();
    }
    return $this->thread_id;
  }

  /**
   * Sets the thread key.
   *
   * @param string $key
   *   The key to set.
   */
  public function setThreadsKey($key) {
    $this->thread_id = $key;
  }

  /**
   * Start processing the assistant synchronously.
   */
  public function process() {
    if (!$this->assistant) {
      throw new \Exception('Assistant is required to process.');
    }
    if (!$this->userMessage) {
      throw new \Exception('Message is required to process.');
    }

    try {
      $pre_prompt = $this->assistant->get('pre_action_prompt');
      if ($pre_prompt) {
        $return = $this->prePrompt();

        // If its a normal response, we just return it.
        if ($return instanceof ChatOutput) {
          return $return;
        }

        // Currently for debugging.
        $defaults = $this->getProviderAndModel();
        foreach ($return['actions'] as $action) {
          $this->using_action = TRUE;
          $instance = $this->actions->createInstance($action['plugin'], $this->assistant->get('actions_enabled')[$action['plugin']] ?? []);
          $instance->setAssistant($this->assistant);
          $instance->setThreadId($this->thread_id);
          $instance->setAiProvider($this->aiProvider->createInstance($defaults['provider_id']));
          $instance->setMessages($this->getMessageHistory());
          $instance->triggerAction($action['action'], $action);
        }
      }
    }
    catch (\Exception $e) {
      // Log the error.
      $this->loggerChannelFactory->get('ai_assistant_api')->error($e->getMessage());
      $error_message = str_replace('[error_message]', $e->getMessage(), $this->assistant->get('error_message'));
      // Return the error message.
      return new ChatOutput(
        new ChatMessage('assistant', $error_message),
        [$error_message],
        [],
      );
    }

    // Run the response to the final assistants message.
    return $this->assistantMessage();
  }

  /**
   * Run the final assistants message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the assistant.
   */
  protected function assistantMessage() {
    $connect = $this->getProviderAndModel();
    $provider = $this->aiProvider->createInstance($connect['provider_id']);
    // Set the provider role.
    $assistant_message = $this->assistant->get('assistant_message');
    // Replace the tokens.
    foreach ($this->tokens as $key => $value) {
      $assistant_message = str_replace('[' . $key . ']', $value, $assistant_message);
    }
    if ($this->using_action) {
      // Add the information that search is done.
      $assistant_message .= "\n\n Start the message with the following information: \nThank you for your question. I am looking up the answer.\n\n";
    }
    // Let other modules change the system role.
    $event = new AiAssistantSystemRoleEvent($assistant_message);
    $this->eventDispatcher->dispatch($event, AiAssistantSystemRoleEvent::EVENT_NAME);
    $assistant_message = $event->getSystemPrompt();
    $provider->setChatSystemRole($assistant_message);

    $messages = [];

    $config = [];
    if ($this->assistant->get('llm_configuration')) {
      foreach ($this->assistant->get('llm_configuration') as $key => $val) {
        $config[$key] = $val;
      }
    }
    $provider->setConfiguration($config);
    if ($this->streaming) {
      $provider->streamedOutput(TRUE);
    }
    // Get the history.
    $history = $this->getMessageHistory();
    foreach ($history as $key => $message) {
      $messages[] = new ChatMessage($message['role'], $message['message']);
    }
    // Set context messages from the actions.
    if (!empty($this->getOutputContexts())) {
      $message = '';
      foreach ($this->getOutputContexts() as $key => $data) {
        $message .= "The following are the results the different actions from the $key action: \n";
        foreach ($data as $item) {
          $message .= $item . "\n";
        }
        $message .= "\n";
      }
      $messages[] = new ChatMessage('assistant', $message);
    }
    $input = new ChatInput($messages);

    $response = $provider->chat($input, $connect['model_id'], [
      'ai_assistant_api',
      'ai_assistant_api_assistant_message',
      'ai_assistant_api_assistant_message_' . $this->assistant->id(),
    ]);

    return $response;
  }

  /**
   * Gets the output contexts.
   *
   * @return array
   *   The output contexts.
   */
  public function getOutputContexts() {
    return $this->getTempStore()->get($this->thread_id)['output_contexts'] ?? [];
  }

  /**
   * Gets the message history.
   *
   * @return array
   *   The message history.
   */
  public function getMessageHistory() {
    if ($this->assistant->get('allow_history') == 'session') {
      return $this->getTempStore()->get($this->thread_id)['messages'] ?? [];
    }
    // Otherwise just return the last message.
    return [
      ['role' => 'user', 'message' => $this->userMessage->getMessage()],
    ];
  }

  /**
   * Helper function to add a message to the session.
   *
   * @param string $role
   *   The role of the message.
   * @param string $message
   *   The message to add.
   */
  protected function addMessageToSession($role, $message) {
    $session = $this->getTempStore()->get($this->thread_id);
    $session['messages'][] = [
      'role' => $role,
      'message' => $message,
    ];
    $this->getTempStore()->set($this->thread_id, $session);
  }

  /**
   * Runs the pre prompt to figure out what to do.
   */
  protected function prePrompt() {
    $pre_prompt = $this->assistant->get('pre_action_prompt');
    $actions = $this->getPreparedActions();
    $pre_prompt = str_replace([
      '[learning_examples]',
      '[list_of_actions]',
      '[pre_prompt]',
      '[system_role]',
    ], [
      $this->getFewShotExamples(),
      $actions,
      $this->assistant->get('preprompt_instructions'),
      $this->assistant->get('system_role'),
    ], $pre_prompt);

    foreach ($this->getPrePromptDrupalContext() as $key => $replace) {
      $pre_prompt = str_replace('[' . $key . ']', $replace, $pre_prompt);
    }

    $event = new PrepromptSystemRoleEvent($pre_prompt);
    $this->eventDispatcher->dispatch($event, PrepromptSystemRoleEvent::EVENT_NAME);
    $pre_prompt = $event->getSystemPrompt();

    $connect = $this->getProviderAndModel();
    $provider = $this->aiProvider->createInstance($connect['provider_id']);
    $provider->setChatSystemRole($pre_prompt);
    if ($this->streaming) {
      $provider->streamedOutput(TRUE);
    }
    $messages = [];
    $history = $this->getMessageHistory();
    foreach ($history as $message) {
      $messages[] = new ChatMessage($message['role'], $message['message']);
    }
    $input = new ChatInput($messages);
    $response = $provider->chat($input, $connect['model_id'], [
      'ai_assistant_api',
      'ai_assistant_api_preprompt',
      'ai_assistant_api_preprompt_' . $this->assistant->id(),
    ]);
    $values = $response->getNormalized();

    $response = $this->promptJsonDecoder->decode($values, 20);

    if (is_array($response)) {
      return $response;
    }
    return new ChatOutput($response, $values, []);
  }

  /**
   * Gets all the few shot examples of the installed actions.
   *
   * @return string
   *   The few shot examples string.
   */
  public function getFewShotExamples() {
    $enabled_actions = $this->assistant->get('actions_enabled');
    $text = '';
    foreach ($enabled_actions as $action => $config) {
      $instance = $this->actions->createInstance($action, $config);
      $examples = $instance->provideFewShotLearningExample();
      foreach ($examples as $example) {
        $text .= $example['description'] . "\n";
        $text .= json_encode($example['schema']) . "\n\n";
      }
    }
    return $text;
  }

  /**
   * Get the private tempstore for AI Assistant.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   */
  public function getTempStore() {
    return $this->tempStore->get('ai_assistant_api');
  }

  /**
   * Is setup.
   *
   * @return bool
   */
  public function isSetup() {
    $connect = $this->getProviderAndModel();
    return !empty($connect);
  }

  /**
   * Check for context matches.
   *
   * @param \Drupal\search_api\Entity\Index $index
   *   The index to check.
   *
   * @return string
   *   If the context matches.
   */
  protected function checkContentContextMatches($index) {
    // Check for context.
    $keys = array_keys($this->context);
    // Check if any of the keys are content entities.
    foreach ($keys as $key) {
      $possible_entity = $this->context[$key];
      if (is_object($possible_entity) && $possible_entity instanceof ContentEntityInterface) {
        // Check if the entity type is in the index.
        if ($index->isValidDatasource('entity:' . $possible_entity->getEntityTypeId())) {
          // Get the bundles for the index.
          $bundles = $index->getDatasource('entity:' . $possible_entity->getEntityTypeId())->getBundles();
          // Check if the bundle is in the index.
          if (in_array($possible_entity->bundle(), array_keys($bundles))) {
            return 'entity:' . $possible_entity->getEntityTypeId() . '/' . $possible_entity->id() . ':' . $possible_entity->language()->getId();
          }
        }
      }
    }
    return "";
  }

  /**
   * Get the provider and model for the assistant.
   *
   * @return array
   *   The provider and model.
   */
  public function getProviderAndModel() {
    $provider_id = $this->assistant->get('llm_provider');
    $model_id = $this->assistant->get('llm_model');
    // If the provider is default, we load the default model.
    if ($provider_id == '__default__') {
      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
        return [];
      }
      $provider_id = $defaults['provider_id'];
      $model_id = $defaults['model_id'];
    }
    return [
      'provider_id' => $provider_id,
      'model_id' => $model_id,
    ];
  }

  /**
   * Get a list of prepared actions.
   *
   * @return string
   *   A string representation of the actions for AI prompts.
   */
  public function getPreparedActions() {
    $actions = $this->actions->listAllActions($this->assistant->get('actions_enabled'));
    $enabled = array_keys($this->assistant->get('actions_enabled'));
    $prepared = '';
    foreach ($actions as $action) {
      if (!in_array($action['plugin'], $enabled)) {
        continue;
      }
      $prepared .= "* action: " . $action['id'] . ", label: " . $action['label'] . ", description: " . $action['description'] . ", plugin: " . $action['plugin'] . "\n";
    }

    $contexts = $this->actions->listAllContexts($this->assistant, $this->thread_id, $this->assistant->get('actions_enabled'));
    if (count($contexts)) {
      $prepared .= "\n";
      $prepared .= "The following are contexts for the actions:\n\n";
      foreach ($contexts as $context) {
        $prepared .= $context['title'] . "\n";
        $prepared .= '* ' . implode("\n* ", $context['description']) . "\n\n";
      }
    }
    return $prepared;
  }

  /**
   * Get preprompt Drupal context.
   *
   * @return string[]
   *   This is the Drupal context that you can add to the pre prompt.
   */
  public function getPrePromptDrupalContext() {
    $context = [];
    $current_request = $this->requestStack->getCurrentRequest();
    $context['is_logged_in'] = $this->currentUser->isAuthenticated() ? 'is logged in' : 'is not logged in';
    $context['user_name'] = $this->currentUser->getAccountName();
    $context['user_roles'] = implode(', ', $this->currentUser->getRoles());
    $context['user_email'] = $this->currentUser->getEmail();
    $context['user_id'] = $this->currentUser->id();
    $context['user_language'] = $this->currentUser->getPreferredLangcode();
    $context['user_timezone'] = $this->currentUser->getTimeZone();
    $context['page_title'] = (string) $this->titleResolver->getTitle($current_request, $current_request->attributes->get('_route_object'));
    $context['page_path'] = $current_request->getRequestUri();
    $context['page_language'] = $this->languageManager->getCurrentLanguage()->getId();
    $context['site_name'] = $this->configFactory->get('system.site')->get('name');

    return $context;
  }

}
