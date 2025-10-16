<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Component\Serialization\Json;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Traits\OperationType\EventDispatcherTrait;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  use EventDispatcherTrait;

  /**
   * The iterator.
   *
   * @var \Traversable
   */
  protected $iterator;

  /**
   * The messages.
   *
   * @var array
   *   The stream chat messages.
   */
  protected $messages = [];

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The finish reason.
   *
   * @var string|null
   */
  protected $finishReason = NULL;

  /**
   * The tool calls used.
   *
   * @var array
   */
  protected $toolCalls = [];

  /**
   * The callbacks to run after the stream is finished.
   *
   * @var callable[]
   */
  protected $callbacks = [];

  /**
   * The Chat Message once set.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage|null
   */
  protected $chatMessage = NULL;

  /**
   * Total token usage.
   *
   * @var int|null
   */
  protected $totalTokenUsage = NULL;

  /**
   * Input token usage.
   *
   * @var int|null
   */
  protected $inputTokenUsage = NULL;

  /**
   * Output token usage.
   *
   * @var int|null
   */
  protected $outputTokenUsage = NULL;

  /**
   * Reasoning token usage.
   *
   * @var int|null
   */
  protected $reasoningTokenUsage = NULL;

  /**
   * Cached token usage.
   *
   * @var int|null
   */
  protected $cachedTokenUsage = NULL;

  /**
   * The created chat output after iteration.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatOutput|null
   */
  protected $chatOutput = NULL;

  /**
   * The original input data sent to the provider.
   *
   * Could be a string, array, or other structured input.
   *
   * @var mixed
   */
  protected $input;

  /**
   * General metadata.
   *
   * @var array
   */
  protected array $metadata = [];

  /**
   * The machine name of the provider used for this stream.
   *
   * Example: "openai", "gemini", etc.
   *
   * @var string|null
   */
  protected ?string $providerId = NULL;

  /**
   * The model identifier used for the request.
   *
   * Example: "gpt-4", "gpt-3.5-turbo", "gemini-pro".
   *
   * @var string|null
   */
  protected ?string $modelId = NULL;

  /**
   * The configuration array passed to the provider.
   *
   * Contains provider-specific options like temperature,
   * max tokens, top_p, etc.
   *
   * @var array
   */
  protected array $providerConfiguration = [];

  /**
   * Tags associated with the stream.
   *
   * Used for categorization, debugging, or tracing events.
   *
   * @var array
   */
  protected array $tags = [];

  /**
   * Constructor.
   */
  public function __construct(\Traversable $iterator) {
    $this->iterator = $iterator;
  }

  /**
   * Sets the original input sent to the provider.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param mixed $input
   *   The input data for this request.
   */
  public function setInput($input): void {
    $this->input = $input;
  }

  /**
   * Gets the original input sent to the provider.
   *
   * @return mixed
   *   The input data.
   */
  public function getInput() {
    return $this->input;
  }

  /**
   * Sets the provider machine name.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param string $providerId
   *   The provider identifier.
   */
  public function setProviderId(string $providerId): void {
    $this->providerId = $providerId;
  }

  /**
   * Gets the provider machine name.
   *
   * @return string|null
   *   The provider identifier, or NULL if not set.
   */
  public function getProviderId(): ?string {
    return $this->providerId;
  }

  /**
   * Sets the model identifier.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param string $modelId
   *   The model ID.
   */
  public function setModelId(string $modelId): void {
    $this->modelId = $modelId;
  }

  /**
   * Gets the model identifier.
   *
   * @return string|null
   *   The model ID, or NULL if not set.
   */
  public function getModelId(): ?string {
    return $this->modelId;
  }

  /**
   * Set the metadata.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $metadata
   *   An associative array of metadata.
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Get the metadata.
   *
   * @return array
   *   An associative array of metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Sets the provider configuration.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $configuration
   *   Provider-specific configuration options.
   */
  public function setProviderConfiguration(array $configuration): void {
    $this->providerConfiguration = $configuration;
  }

  /**
   * Gets the provider configuration.
   *
   * @return array
   *   Provider configuration array.
   */
  public function getProviderConfiguration(): array {
    return $this->providerConfiguration;
  }

  /**
   * Sets tags for the stream.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $tags
   *   An array of tags.
   */
  public function setTags(array $tags): void {
    $this->tags = $tags;
  }

  /**
   * Gets tags for the stream.
   *
   * @return array
   *   An array of tags.
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    foreach ($this->doIterate() as $data) {
      yield $data;
    }
    $this->reconstructChatOutput();
    $this->triggerEvent();

    foreach ($this->callbacks as $callback) {
      if (is_callable($callback)) {
        $return = $callback($this->chatMessage);
        // If the callback return a streamed chat message, we yield it.
        if ($return instanceof StreamedChatMessageIteratorInterface) {
          foreach ($return as $streamed_message) {
            yield $streamed_message;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    // We keep this empty, so anyone extending from 1.2.0 can implement
    // their own logic.
    yield $this->createStreamedChatMessage(
      'assistant',
      'Please implement the doIterate method in your provider.',
      [],
    );
  }

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void {
    // Dispatch the event.
    $event = new PostStreamingResponseEvent(
      requestThreadId: $this->requestThreadId ?? '',
      providerId: $this->providerId ?? '',
      operationType: $this->operationType ?? '',
      configuration: $this->configuration ?? [],
      input: $this->input ?? '',
      modelId: $this->modelId ?? '',
      output: $this->chatOutput,
      tags: $this->tags ?? [],
      debugData: $this->debugData ?? [],
      metadata: $this->metadata ?? []
    );

    $this->getEventDispatcher()->dispatch($event, PostStreamingResponseEvent::EVENT_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestThreadId(string $request_thread_id): void {
    $this->requestThreadId = $request_thread_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestThreadId(): string {
    return $this->requestThreadId;
  }

  /**
   * {@inheritdoc}
   */
  public function createStreamedChatMessage(
    string $role,
    string $message,
    array $metadata,
    ?array $tools = NULL,
    ?array $raw = NULL,
  ): StreamedChatMessageInterface {
    $message = new StreamedChatMessage($role, $message, $metadata, $tools, $raw);
    $this->messages[] = $message;
    return $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamChatMessages(): array {
    return $this->messages;
  }

  /**
   * {@inheritdoc}
   */
  public function addCallback(callable $callback): void {
    $this->callbacks[] = $callback;
  }

  /**
   * {@inheritdoc}
   */
  public function setStreamChatMessages(array $messages): void {
    $this->messages = $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function reconstructChatOutput(): ChatOutput {
    // Create a ChatMessage out of it all.
    $role = '';
    $message_text = '';
    $raw = [];
    foreach ($this->messages as $message) {
      // The role only needs to be set once, so we check if it's empty.
      if (!empty($message->getRole()) && empty($role)) {
        $role = $message->getRole();
      }
      // Just accumulate the text.
      if (!empty($message->getText())) {
        $message_text .= $message->getText();
      }
      // We assume that we can combine, any external provider can override this
      // if needed.
      if (!empty($message->getRaw())) {
        $raw = array_merge($raw, $message->getRaw());
      }

      // Set the total usage, if it exists.
      $this->setTokenUsageFromChunk($message);
    }

    $message = new ChatMessage($role, $message_text);
    $message->setTools($this->assembleToolCalls());

    // Set the chat message.
    $this->chatMessage = $message;

    $output = new ChatOutput($message, $raw, []);
    // Set the token usage on the output.
    $output = $this->setTokenUsageOnChatOutput($output);

    $this->chatOutput = $output;
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return $this->assembleToolCalls();
  }

  /**
   * Assembled the tools calls used - OpenAI style, can be overriden.
   *
   * @return array
   *   The tool calls used.
   */
  private function assembleToolCalls(): array {
    $tools = [];
    $key = 0;
    $current_tool = NULL;
    foreach ($this->messages as $message) {
      if ($message->getTools()) {
        foreach ($message->getTools() as $tool) {
          $array_tool = $tool->toArray();
          // If it has a new id, it means its a new tool call.
          if (!empty($array_tool['id'])) {
            // If the current tool is not empty, we need to save it.
            if (!empty($current_tool)) {
              $arguments = Json::decode($current_tool['function']['arguments']);
              $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
              $output->setName($current_tool['function']['name']);
              $tools[$key] = $output;
              $key++;
            }
            // Reset the current tool.
            $current_tool = $array_tool;
          }
          else {
            // Otherwise we just add to the argument of the current tool.
            $current_tool['function']['arguments'] .= $array_tool['function']['arguments'] ?? '';
          }
        }
      }
    }
    // Save the last tool if it exists.
    if (!empty($current_tool)) {
      $arguments = Json::decode($current_tool['function']['arguments']);
      $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
      $output->setName($current_tool['function']['name']);
      $tools[$key] = $output;
    }

    return $tools;
  }

  /**
   * Set the token usage from each chunk.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface $message
   *   The streamed chat message to set the token usage on.
   */
  protected function setTokenUsageFromChunk(StreamedChatMessageInterface $message): void {
    if ($message->getTotalTokenUsage() !== NULL) {
      $this->totalTokenUsage = $message->getTotalTokenUsage();
    }
    if ($message->getInputTokenUsage() !== NULL) {
      $this->inputTokenUsage = $message->getInputTokenUsage();
    }
    if ($message->getOutputTokenUsage() !== NULL) {
      $this->outputTokenUsage = $message->getOutputTokenUsage();
    }
    if ($message->getReasoningTokenUsage() !== NULL) {
      $this->reasoningTokenUsage = $message->getReasoningTokenUsage();
    }
    if ($message->getCachedTokenUsage() !== NULL) {
      $this->cachedTokenUsage = $message->getCachedTokenUsage();
    }
  }

  /**
   * Set the token usage on the chat output.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $output
   *   The chat output to set the token usage on.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output with the token usage set.
   */
  protected function setTokenUsageOnChatOutput(ChatOutput $output): ChatOutput {
    $output->setTokenUsage(new TokenUsageDto(
      total: $this->totalTokenUsage,
      input: $this->inputTokenUsage,
      output: $this->outputTokenUsage,
      reasoning: $this->reasoningTokenUsage,
      cached: $this->cachedTokenUsage
    ));
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function setFinishReason(string $finished_reason): void {
    $this->finishReason = $finished_reason;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishReason(): ?string {
    return $this->finishReason;
  }

}
