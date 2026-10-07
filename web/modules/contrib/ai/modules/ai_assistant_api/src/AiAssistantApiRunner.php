<?php

namespace Drupal\ai_assistant_api;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Access\AccessException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\SessionManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\TempStore\TempStoreException;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai_assistant_api\Data\UserMessage;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use Drupal\ai_assistant_api\Event\AiAssistantSystemRoleEvent;
use Drupal\ai_assistant_api\Service\AssistantMessageBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * The runner for the AI assistant.
 */
class AiAssistantApiRunner {

  /**
   * The assistant.
   *
   * @var \Drupal\ai_assistant_api\Entity\AiAssistant|null
   */
  protected AiAssistant|NULL $assistant = NULL;

  /**
   * The message to send to the assistant.
   *
   * @var \Drupal\ai_assistant_api\Data\UserMessage|null
   */
  protected UserMessage|NULL $userMessage;

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
  protected string $threadId = '';

  /**
   * Let the system know if an action is being used.
   *
   * @var bool
   */
  protected bool $usingAction = FALSE;

  /**
   * If it should throw exception on errors.
   *
   * @var bool
   */
  protected bool $throwException = FALSE;

  /**
   * If the verbose mode is enabled.
   *
   * @var bool
   */
  protected bool $verboseMode = FALSE;

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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The message to json service.
   * @param \Drupal\ai_assistant_api\Service\AssistantMessageBuilder $assistantMessageBuilder
   *   The assistant message builder.
   * @param \Drupal\Core\Session\SessionManagerInterface $sessionManager
   *   The session manager.
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AiProviderPluginManager $aiProvider,
    protected Renderer $renderer,
    protected PrivateTempStoreFactory $tempStore,
    protected AiAssistantActionPluginManager $actions,
    protected EventDispatcherInterface $eventDispatcher,
    protected AccountProxyInterface $currentUser,
    protected LoggerChannelFactoryInterface $loggerChannelFactory,
    protected PromptJsonDecoderInterface $promptJsonDecoder,
    protected AssistantMessageBuilder $assistantMessageBuilder,
    protected SessionManagerInterface $sessionManager,
    protected Settings $settings,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * Gets the assistant.
   *
   * @return \Drupal\ai_assistant_api\Entity\AiAssistant
   *   The assistant.
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

    // Generate the thread id.
    if ($this->shouldStoreSession() && !$this->threadId) {
      $this->threadId = $this->generateUniqueKey();
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
   * Gets the context.
   *
   * @return array
   *   The context.
   */
  public function getContext() {
    return $this->context;
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
    if ($this->shouldStoreSession() && $this->userMessage->getMessage() !== 'dummy_loading') {
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
    if ($this->shouldStoreSession()) {
      $this->addMessageToSession('assistant', $message);
    }
  }

  /**
   * Gets a unique storage key for the assistant.
   *
   * @return string
   *   The unique key.
   */
  public function generateUniqueKey() {
    $chatMemory = $this->assistant->getChatMemory();
    // A plugin with a persistent thread always resolves to the same thread
    // id for this owner, via a sticky pointer kept alongside it, so it
    // survives across requests without the client having to carry it.
    if ($chatMemory !== NULL && $chatMemory->hasPersistentThread()) {
      if ($this->getCurrentThreadsKey()) {
        return $this->getCurrentThreadsKey();
      }
      $current = $chatMemory->createThreadId();
      $this->setCurrentThreadsKey($current);
      return $current;
    }
    if ($chatMemory === NULL) {
      return $this->generateUniqueHash();
    }
    // Otherwise, keep a bounded, rotating pool of concurrent threads per
    // owner instead of a single persistent one: reuse the first free slot,
    // or evict the oldest once the pool is full. This is a stopgap; real
    // garbage collection would be an improvement.
    $uid = $this->currentUser->id();
    $pool_size = 10;
    for ($i = 0; $i <= $pool_size; $i++) {
      $key = "assistant_thread_{$uid}_{$i}";
      if (!$chatMemory->hasThread($key)) {
        return $key;
      }
    }
    $evicted_key = "assistant_thread_{$uid}_0";
    $chatMemory->deleteThread($evicted_key);
    return $evicted_key;
  }

  /**
   * Gets the thread id.
   *
   * @return string
   *   The thread id.
   */
  public function getThreadsKey() {
    if (!$this->threadId) {
      $this->threadId = $this->generateUniqueKey();
    }
    return $this->threadId;
  }

  /**
   * Sets the thread key.
   *
   * @param string $key
   *   The key to set.
   */
  public function setThreadsKey($key) {
    $this->threadId = $key;
  }

  /**
   * Unset the thread key.
   */
  public function unsetThreadsKey() {
    $this->threadId = '';
  }

  /**
   * Start processing the assistant synchronously.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the assistant or error.
   */
  public function process() {
    // Validate that we can run.
    $this->validateAssistant();

    // Reset everything before running.
    $this->resetStructuredResults();
    $this->resetOutputContexts();
    $instance = NULL;

    // If we are using an agent as assistant.
    if ($this->assistant->get('ai_agent') && $this->moduleHandler->moduleExists('ai_agents')) {
      // Use the agent to run the task, kid of anti pattern in requirement.
      // @phpstan-ignore-next-line
      return \Drupal::service('ai_assistant_api.agent_runner')->runAsAgent( // phpcs:ignore
        $this->assistant->get('ai_agent'),
        $this->getMessageHistory(),
        $this->getProviderAndModel(),
        $this->getThreadsKey(),
        $this->getVerboseMode(),
        $this->getContext(),
      );
    }

    try {
      $system_prompt = $this->assistant->get('system_prompt');
      // If the site isn't configured to use custom prompts, override with the
      // latest version of the prompt from the module.
      if (!$this->settings->get('ai_assistant_custom_prompts', FALSE)) {
        $path = $this->moduleHandler->getModule('ai_assistant_api')->getPath() . '/resources/';
        $system_prompt = file_get_contents($path . 'system_prompt.txt');
      }
      if ($system_prompt) {
        $return = $this->assistantMessage(TRUE);

        // If it's a normal response, we just return it.
        if ($return instanceof ChatOutput) {
          return $return;
        }

        $defaults = $this->getProviderAndModel();

        // $return is only reached here when the prompt JSON decoder decided
        // this looked like an action plan; guard against it lacking the key
        // anyway (e.g. a provider whose reply happens to embed a JSON-looking
        // substring without ever intending an action plan).
        foreach ($return['actions'] ?? [] as $action) {
          $this->usingAction = TRUE;
          $instance = $this->actions->createInstance($action['plugin'], $this->assistant->get('actions_enabled')[$action['plugin']] ?? []);
          $instance->setAssistant($this->assistant);
          $instance->setThreadId($this->threadId);
          $instance->setAiProvider($this->aiProvider->createInstance($defaults['provider_id']));
          $instance->setMessages($this->getMessageHistory());
          // Pass the assistant and the thread id so it can be tagged.
          $action['ai_assistant_api'] = $this->assistant->id();
          $action['thread_id'] = $this->threadId;
          $instance->triggerAction($action['action'], $action);
        }
      }
    }
    catch (\Exception $e) {
      // Log the error.
      $this->loggerChannelFactory->get('ai_assistant_api')->error($e->getMessage());
      $error_message = str_replace('[error_message]', $e->getMessage(), $this->assistant->get('error_message'));
      if (!is_null($instance)) {
        $instance->triggerRollback();
      }
      if ($this->throwException) {
        // Throw the existing exception to maintain the type.
        throw $e;
      }
      // Return the error message.
      return new ChatOutput(
        new ChatMessage('assistant', $error_message),
        [$error_message],
        [],
      );
    }

    // Run the response to the final assistants message. The prompt JSON
    // decoder can still mistake plain text for a JSON action plan here (for
    // example, a provider whose reply happens to embed a JSON-looking
    // substring), in which case there is nothing left to act on: unlike the
    // pre-action-prompt call above, an array result at this point is never
    // expected to carry further actions, so fall back to displaying it
    // rather than handing a plain array to callers that assume a ChatOutput.
    $final = $this->assistantMessage();
    if ($final instanceof ChatOutput) {
      return $final;
    }
    $this->loggerChannelFactory->get('ai_assistant_api')->warning('The final assistant response was decoded as a JSON action plan instead of a plain message; falling back to a raw display of the decoded value.');
    return new ChatOutput(new ChatMessage('assistant', json_encode($final)), [$final], []);
  }

  /**
   * Validate that its possible to run the assistant.
   */
  protected function validateAssistant() {
    // Check if the assistant is set.
    if (!$this->assistant) {
      throw new \Exception('Assistant is required to process.');
    }
    // Check if the user message is set.
    if (!$this->userMessage) {
      throw new \Exception('Message is required to process.');
    }
    // Check permissions.
    if (!$this->userHasAccess()) {
      throw new \Exception('User does not have the required role to run the assistant.');
    }
  }

  /**
   * Check if the user has the required role to run the assistant.
   *
   * @return bool
   *   If the user has the required role.
   */
  public function userHasAccess() {
    if ($this->currentUser->id() == 1) {
      return TRUE;
    }
    $roles = $this->assistant->get('roles');
    $chosen_roles = [];
    foreach ($roles as $role => $value) {
      if ($value) {
        $chosen_roles[] = $role;
      }
    }
    // Check if they have values.
    if (count($chosen_roles)) {
      if ($this->currentUser->isAnonymous() && isset($chosen_roles['anonymous'])) {
        return TRUE;
      }
      else {
        /** @var \Drupal\user\UserInterface */
        $account = $this->currentUser->getAccount();
        foreach ($roles as $role => $value) {
          if ($value && $account->hasRole($role)) {
            return TRUE;
          }
        }
      }
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Run the final assistants message.
   *
   * @param bool $pre_prompt
   *   If the pre prompt should be run.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The response from the assistant.
   */
  protected function assistantMessage($pre_prompt = FALSE) {
    $connect = $this->getProviderAndModel();
    $provider = $this->aiProvider->createInstance($connect['provider_id']);
    $assistant_message = $this->assistantMessageBuilder->buildMessage($this->assistant, $this->threadId, $pre_prompt, $this->context);
    // Let other modules change the system role.
    $event = new AiAssistantSystemRoleEvent($assistant_message);
    $this->eventDispatcher->dispatch($event, AiAssistantSystemRoleEvent::EVENT_NAME);
    $assistant_message = $event->getSystemPrompt();

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
    }
    else {
      $message = "No actions have been run, this means that you have done nothing since the last instruction.";
    }
    $assistant_message = $assistant_message . $message;

    $messages = [];

    $config = [];
    if ($this->assistant->get('llm_configuration')) {
      foreach ($this->assistant->get('llm_configuration') as $key => $val) {
        $config[$key] = $val;
      }
    }
    $provider->setConfiguration($config);

    // Get the history.
    $history = $this->getMessageHistory();
    foreach ($history as $key => $message) {
      $messages[] = new ChatMessage($message['role'], $message['message']);
    }
    $input = new ChatInput($messages);
    if ($this->streaming) {
      $input->setStreamedOutput(TRUE);
    }
    $input->setSystemPrompt($assistant_message);
    // If its preprompt and function calling, we set the function calling.
    if ($pre_prompt && $this->assistant->get('use_function_calling')) {
      $tools = $this->assistantMessageBuilder->getFunctionCalls();
      $input->setChatTools($tools);
    }

    $tags = [
      'ai_assistant_api',
      'ai_assistant_api_assistant_message',
      'ai_assistant_api_assistant_message_' . $this->assistant->id(),
      'ai_assistant_thread_' . $this->threadId,
    ];

    if ($pre_prompt) {
      $tags[] = 'ai_assistant_api_pre_prompt';
    }

    $response = $provider->chat($input, $connect['model_id'], $tags);
    $values = $response->getNormalized();

    // If it's using function calling, and the provider has tools, use them.
    if (method_exists($values, 'getTools')) {
      // Output the tools if they exist.
      $tools = $values->getTools();
    }
    $response = $this->promptJsonDecoder->decode($values, 20);

    if (is_array($response)) {
      return $response;
    }
    return new ChatOutput($response, $values, []);
  }

  /**
   * Gets the output contexts.
   *
   * @return array
   *   The output contexts.
   */
  public function getOutputContexts() {
    return $this->getTempStore()->get($this->threadId)['output_contexts'] ?? [];
  }

  /**
   * Reset the output contexts.
   */
  public function resetOutputContexts() {
    $session = $this->getTempStore()->get($this->threadId);
    $session['output_contexts'] = [];
    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Gets the output data structure.
   *
   * @return array
   *   The output structured results.
   */
  public function getStructuredResults() {
    return $this->getTempStore()->get($this->threadId)['structured_results'] ?? [];
  }

  /**
   * Resets the output data structure.
   */
  public function resetStructuredResults() {
    $session = $this->getTempStore()->get($this->threadId);
    $session['structured_results'] = [];
    $this->getTempStore()->set($this->threadId, $session);
  }

  /**
   * Gets the message history.
   *
   * @return array
   *   The message history.
   */
  public function getMessageHistory() {
    $chatMemory = $this->assistant->getChatMemory();
    if ($chatMemory !== NULL) {
      return array_map(fn (ChatMessage $message) => [
        'role' => $message->getRole(),
        'message' => $message->getText(),
        'timestamp' => $message->getTimestamp(),
      ], $chatMemory->loadMessages($this->threadId));
    }
    // Otherwise just return the last message.
    if (isset($this->userMessage) && $this->userMessage instanceof UserMessage) {
      return [
        ['role' => 'user', 'message' => $this->userMessage->getMessage()],
      ];
    }
    // No message yet, return empty history.
    return [];
  }

  /**
   * Reset the message history.
   */
  public function resetMessageHistory() {
    $this->assistant->getChatMemory()?->clearMessages($this->threadId);
  }

  /**
   * Reset a whole thread and get a new thread id.
   *
   * @param string $thread_id
   *   The thread id to reset.
   *
   * @return string
   *   The new thread id.
   */
  public function resetThread($thread_id) {
    $this->setThreadsKey($thread_id);
    $chatMemory = $this->assistant->getChatMemory();
    try {
      if ($chatMemory === NULL || !$chatMemory->hasThread($thread_id)) {
        throw new ResourceNotFoundException();
      }
      $chatMemory->deleteThread($thread_id);
      // Also clean up the runner's own per-thread bookkeeping (output
      // contexts, structured results, action contexts) that lives outside
      // chat memory, in the same tempstore row keyed by the thread id.
      $this->getTempStore()->delete($thread_id);
    }
    catch (TempStoreException) {
      throw new AccessException();
    }
    $this->removeCurrentThreadsKey();
    $this->unsetThreadsKey();
    return $this->getThreadsKey();
  }

  /**
   * Get the current thread id.
   *
   * @return string
   *   The current thread id.
   */
  public function getCurrentThreadsKey() {
    return $this->getTempStore()->get('current_thread_id_' . $this->assistant->id());
  }

  /**
   * Set the current thread id.
   *
   * @param string $thread_id
   *   The thread id to set.
   */
  public function setCurrentThreadsKey($thread_id) {
    $this->getTempStore()->set('current_thread_id_' . $this->assistant->id(), $thread_id);
  }

  /**
   * Remove the current thread id.
   */
  public function removeCurrentThreadsKey() {
    $this->getTempStore()->delete('current_thread_id_' . $this->assistant->id());
  }

  /**
   * Sets if it should throw exception on errors.
   *
   * @param bool $throw
   *   If it should throw exception on errors.
   */
  public function setThrowException(bool $throw) {
    $this->throwException = $throw;
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
    $this->assistant->getChatMemory()?->appendMessages($this->threadId, [
      new ChatMessage($role, $message, [], time()),
    ]);
  }

  /**
   * Start temporary storage for the assistant.
   */
  public function startSession() {
    if ($this->shouldStoreSession()) {
      $this->sessionManager->start();
    }
  }

  /**
   * Should store session.
   *
   * @return bool
   *   If the session should be stored.
   */
  public function shouldStoreSession() {
    return $this->assistant->getChatMemory() !== NULL;
  }

  /**
   * Get the private tempstore for AI Assistant.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStore
   *   The tempstore.
   */
  public function getTempStore() {
    return $this->tempStore->get('ai_assistant_api');
  }

  /**
   * Is setup.
   *
   * @return bool
   *   If the assistant is setup.
   */
  public function isSetup() {
    $connect = $this->getProviderAndModel();
    return !empty($connect);
  }

  /**
   * Generate a unique hash.
   *
   * @return string
   *   The unique hash.
   */
  public function generateUniqueHash() {
    return Crypt::hashBase64(uniqid('ai-assistant', TRUE) . microtime(TRUE));
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
   * Get the verbose mode.
   *
   * @return bool
   *   If the verbose mode is enabled.
   */
  public function getVerboseMode() {
    return $this->verboseMode;
  }

  /**
   * Set the verbose mode.
   *
   * @param bool $verbose
   *   If the verbose mode should be enabled.
   */
  public function setVerboseMode(bool $verbose) {
    $this->verboseMode = $verbose;
  }

}
