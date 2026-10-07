<?php

declare(strict_types=1);

namespace Drupal\ai_chatbot\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface;
use Drupal\ai\PluginManager\ChatProcessorPluginManager;
use Drupal\ai\Response\AiStreamedResponse;
use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;
use Drupal\ai_chatbot\Service\MessagesButtons;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for AI Deepchat routes.
 */
class DeepChatApi extends ControllerBase {

  /**
   * Reserved execution identity fields that callers are not allowed to submit.
   *
   * Follow-up under #3573899: centralize execution envelope reserved fields so
   * all entry points enforce the same contract.
   */
  private const RESERVED_EXECUTION_IDENTITY_FIELDS = [
    'executor_uid',
    'initiator_uid',
    'initiator_subject',
  ];

  /**
   * All buttons available for the assistant.
   *
   * @var array
   */
  protected array $buttons = [];

  /**
   * Allowed tags for rendered LLM output.
   *
   * We allow anything that can be expressed in markdown. Shared with the
   * history rendering in DeepChatFormBlock so both paths sanitize alike.
   */
  public const ALLOWED_TAGS = [
    'a',
    'b',
    'blockquote',
    'br',
    'code',
    'del',
    'em',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'i',
    'img',
    'li',
    'ol',
    'p',
    'pre',
    'strong',
    'ul',
  ];

  /**
   * Constructs a new DeepChatApi object.
   *
   * @param \Drupal\ai_chatbot\Service\MessagesButtons $messagesButtons
   *   The messages buttons render service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfTokenGenerator
   *   The CSRF token generator.
   * @param \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager $functionCallPluginManager
   *   The function call plugin manager.
   * @param \Drupal\ai\PluginManager\ChatProcessorPluginManager $chatProcessorPluginManager
   *   The chat processor plugin manager.
   * @param \League\CommonMark\CommonMarkConverter $commonMarkConverter
   *   The CommonMark converter.
   */
  public function __construct(
    protected MessagesButtons $messagesButtons,
    protected CsrfTokenGenerator $csrfTokenGenerator,
    protected FunctionCallPluginManager $functionCallPluginManager,
    protected ChatProcessorPluginManager $chatProcessorPluginManager,
    protected CommonMarkConverter $commonMarkConverter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('ai_chatbot.buttons'),
      $container->get('csrf_token'),
      $container->get('plugin.manager.ai.function_calls'),
      $container->get(ChatProcessorPluginManager::class),
      $container->get('ai.commonmark_converter'),
    );
  }

  /**
   * Handles the DeepChat API request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Drupal\ai\Response\AiStreamedResponse
   *   The JSON or Streamed response.
   */
  public function api(Request $request): JsonResponse|AiStreamedResponse {
    // Get the request content and decode it.
    $content = $request->getContent();
    $data = Json::decode($content);

    // Reject caller-supplied execution identity fields at the API boundary.
    $payload_keys = array_keys(is_array($data) ? $data : []);
    $forbidden_fields = array_intersect(self::RESERVED_EXECUTION_IDENTITY_FIELDS, $payload_keys);
    if ($forbidden_fields !== []) {
      return new JsonResponse([
        'error' => $this->t('Client-supplied execution identity fields are not allowed: @fields', [
          '@fields' => implode(', ', $forbidden_fields),
        ]),
      ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // Check if 'messages' array is provided.
    if (isset($data['messages']) && is_array($data['messages'])) {
      $messages = $data['messages'];

      // Extract user messages.
      $conversation = [];
      foreach ($messages as $message) {
        if (isset($message['role'], $message['text'])) {
          $role = $message['role'];
          $text = $message['text'];
          if ($role === 'user') {
            $conversation[] = new ChatMessage('user', $text);
          }
          elseif ($role === 'assistant') {
            $conversation[] = new ChatMessage('assistant', $text);
          }
        }
      }

      if (empty($conversation)) {
        return new JsonResponse(['error' => $this->t('No user messages provided.')], 400);
      }

      // Check so the chat processor plugin is set.
      $chat_processor_plugin = $data['chat_processor_plugin'] ?? NULL;
      if (empty($chat_processor_plugin) || !$this->chatProcessorPluginManager->hasDefinition($chat_processor_plugin)) {
        return new JsonResponse(['error' => $this->t('Invalid or missing chat processor plugin.')], 400);
      }

      $input = new ChatInput($conversation);

      if (isset($data['stream']) && $data['stream'] === 1) {
        $input->setStreamedOutput(TRUE);
      }
      // Set the context if provided.
      if (isset($data['contexts']) && is_array($data['contexts'])) {
        $input->setRequestMetadataValue('contexts', $data['contexts']);
      }

      $plugin_configuration = $data['plugin_configuration'] ?? [];

      // The identifier passed to hook_deepchat_prepend_message(). Assistant
      // backed processors keep passing the assistant id for backwards
      // compatibility; other processors pass their plugin id.
      $hook_id = !empty($plugin_configuration['assistant_id']) ? $plugin_configuration['assistant_id'] : $chat_processor_plugin;

      // Process the user's message.
      try {
        // Load the chat processor plugin.
        /** @var \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $chat_processor */
        $chat_processor = $this->chatProcessorPluginManager->createInstance($chat_processor_plugin, $plugin_configuration);

        // Let the plugin enforce its own access rules on top of the route
        // permission.
        $access = $chat_processor->access($this->currentUser());
        if (!$access->isAllowed()) {
          return new JsonResponse(['error' => $this->t('You do not have access to this chatbot.')], 403);
        }

        // Set the input messages.
        $chat_processor->setInput($input);
        // Optionally, set the thread_id if provided.
        if (isset($data['thread_id'])) {
          $chat_processor->setThreadId($data['thread_id']);
        }
        // Execute the chat processor.
        $chat_processor->execute();
        $response = $chat_processor->getOutput();

        // The processor owns the thread id; use it for the prepend hook.
        $thread_id = $chat_processor->getThreadId() ?? '';

        // Handle the response, which could be a ChatMessage or Stream.
        $normalized_response = $response->getNormalized();

        // Decide response type based on the request.
        if ($normalized_response instanceof ChatMessage) {
          return $this->createResponse($normalized_response, $chat_processor, $hook_id, $thread_id);
        }
        else {
          return $this->createStreamedResponse($normalized_response, $chat_processor, $hook_id, $thread_id);
        }
      }
      catch (\Exception $e) {
        // Log the exception.
        $this->getLogger('ai_chatbot')->error('DeepChat API error: @message', ['@message' => $e->getMessage()]);
        // Otherwise use the default error message.
        return new JsonResponse([
          'error' => $this->t('An error occurred while processing the request. Please try again later.'),
        ], 500);

      }
    }
    else {
      // No messages provided in the request.
      return new JsonResponse(['error' => $this->t('No messages provided.')], 400);
    }
  }

  /**
   * Returns a normal response.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage $response
   *   The assistant response.
   * @param \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface|null $chat_processor
   *   The chat processor that produced the response.
   * @param string $hook_id
   *   The conversation identifier passed to hook_deepchat_prepend_message()
   *   (the assistant id for assistant-backed processors, otherwise the
   *   processor plugin id).
   * @param string $thread_id
   *   The conversation thread id.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response.
   */
  public function createResponse(ChatMessage $response, ?ChatProcessorInterface $chat_processor = NULL, string $hook_id = '', string $thread_id = ''): JsonResponse {
    $response_text = $response->getText();

    // Let modules append extra messages to the response.
    $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
      $response_text,
      'text',
      $hook_id,
      $thread_id,
    ]);

    $converter = $this->getCommonMarkConverter();
    $response_text = $this->rewriteMarkdownMessage($response_text);
    $response_text = $converter->convert($response_text)->getContent();
    // The model output is untrusted, so sanitize it. Everything appended
    // after this point is controlled by modules and plugins.
    $response_text = Xss::filter($response_text, self::ALLOWED_TAGS);
    if (empty($response->getTools())) {
      if ($chat_processor) {
        $response_text .= $chat_processor->getPostResponseMarkup();
      }
      foreach ($extra as $extra_message) {
        $response_text .= $extra_message;
      }
    }
    else {
      // If its empty string, we return that a tool was used.
      $tools = [];
      foreach ($response->getTools() as $tool) {
        $tools[$tool->getName()] = $this->functionCallPluginManager->getFunctionCallFromFunctionName($tool->getName())->getPluginDefinition()['name'] ?? $tool->getName();
      }
      $response_text = $this->t('Calling @tools', [
        '@tools' => implode(', ', $tools),
      ]) . $response_text;

    }

    // Default to JSON response.
    return new JsonResponse([
      'html' => $response_text,
      'should_continue' => !empty($response->getTools()),
    ]);
  }

  /**
   * Creates a StreamedResponse for the assistant's reply.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $message
   *   The text generated by the AI assistant.
   * @param \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface $chat_processor
   *   The chat processor that produced the stream.
   * @param string $hook_id
   *   The conversation identifier passed to hook_deepchat_prepend_message().
   * @param string $thread_id
   *   The conversation thread id.
   *
   * @return \Drupal\ai\Response\AiStreamedResponse
   *   The streamed response.
   */
  private function createStreamedResponse(StreamedChatMessageIteratorInterface $message, ChatProcessorInterface $chat_processor, string $hook_id = '', string $thread_id = ''): AiStreamedResponse {
    $response = new AiStreamedResponse(NULL, 200, [
      'Content-Type' => 'text/event-stream',
    ]);

    $response->setCallback(function () use ($message, $chat_processor, $hook_id, $thread_id) {
      $save_message = '';

      // Make sure to start session.
      foreach ($message as $chunk) {
        $save_message .= $chunk->getText();
        // Send each chunk.
        $this->createSseMessage($save_message, TRUE);
      }

      // Hand the full text back so the plugin can persist it to history.
      $chat_processor->onStreamComplete($save_message);

      // Send the plugin's trusted post-response markup (e.g. structured
      // results).
      $post_markup = $chat_processor->getPostResponseMarkup();
      if ($post_markup !== '') {
        $this->createSseMessage($post_markup);
      }

      // Let modules append extra messages to the response.
      $extra = $this->moduleHandler()->invokeAll('deepchat_prepend_message', [
        $save_message,
        'text',
        $hook_id,
        $thread_id,
      ]);
      foreach ($extra as $extra_message) {
        $this->createSseMessage($extra_message);
      }
    });

    return $response;
  }

  /**
   * Gets an x-csrf-token back.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The response object.
   */
  public function setSession(Request $request): Response {
    $session = $request->getSession();
    // Create a session for the user if they are anonymous.
    if (!$session->isStarted()) {
      $session->start();
    }
    // Set a session variable.
    $session->set('deepchat', 'true');
    return new Response($this->csrfTokenGenerator->get("api/deepchat"));
  }

  /**
   * Rewrite markdown message.
   *
   * @param string $message
   *   The message to rewrite.
   *
   * @return string
   *   The rewritten message.
   */
  public function rewriteMarkdownMessage(string $message): string {
    // Find any link from a markdown message an iterate them.
    $pattern = '/\[(.*?)\]\((.*?)\)/';
    preg_match_all($pattern, $message, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $link = trim($match[2]);
      $text = $match[1];
      // If the link does not start with a protocol or slash, add the base URL.
      if (strpos($link, '://') === FALSE && strpos($link, '/') !== 0) {
        $link = base_path() . $link;
      }
      // Sometimes it adds double slashes at the start.
      if (substr($link, 0, 2) === '//') {
        $link = substr($link, 1);
      }

      $message = str_replace($match[0], "[$text]($link)", $message);
    }

    return $message;
  }

  /**
   * Send SSE message.
   *
   * @param string $message
   *   The message to send.
   * @param bool $is_chunk
   *   If the message is a chunk.
   * @param string $type
   *   The type of message.
   */
  public function createSseMessage(string $message, bool $is_chunk = FALSE, string $type = 'html'): void {
    $message = $this->rewriteMarkdownMessage($message);
    $converter = $this->getCommonMarkConverter();
    if ($is_chunk) {
      // Send the chunk.
      $html = $converter->convert($message)->getContent();
      // This part is generated by the LLM, so sanitize it.
      $html = Xss::filter($html, self::ALLOWED_TAGS);
      if ($html) {
        echo 'data: ' . Json::encode([$type => $html, 'overwrite' => TRUE]) . "\n\n";
        flush();
      }
    }
    else {
      echo 'data: ' . Json::encode([$type => $message]) . "\n\n";
      flush();
    }
  }

  /**
   * Gets the CommonMark converter.
   *
   * @return \League\CommonMark\CommonMarkConverter
   *   The CommonMark converter.
   */
  public function getCommonMarkConverter(): object {
    return $this->commonMarkConverter;
  }

}
