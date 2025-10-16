<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * For streaming chat message.
 */
interface StreamedChatMessageIteratorInterface extends \IteratorAggregate {

  public function __construct(\IteratorAggregate $iterator);

  /**
   * The getIterator method to return a generator.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Move all logic to
   * doIterate() instead.
   * @see https://www.drupal.org/project/ai/issues/3538341
   *
   * @return \Generator
   *   A generator that yields streamed chat messages.
   */
  public function getIterator(): \Generator;

  /**
   * The actual implementation of the generator.
   *
   * @return \Generator
   *   A generator that yields streamed chat messages.
   */
  public function doIterate(): \Generator;

  /**
   * Set an request thread id.
   *
   * @param string $request_thread_id
   *   The request thread id.
   */
  public function setRequestThreadId(string $request_thread_id): void;

  /**
   * Get the request thread id.
   *
   * @return string
   *   The request thread id.
   */
  public function getRequestThreadId(): string;

  /**
   * Sets on stream chat message.
   *
   * @param string $role
   *   The role.
   * @param string $message
   *   The message.
   * @param array $metadata
   *   The metadata.
   * @param array|null $tools
   *   The tools.
   * @param array|null $raw
   *   The raw data.
   *
   * @return \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface
   *   The streamed chat message.
   */
  public function createStreamedChatMessage(string $role, string $message, array $metadata, ?array $tools = NULL, ?array $raw = NULL,): StreamedChatMessageInterface;

  /**
   * Gets the stream chat messages.
   *
   * @return array
   *   The stream chat messages.
   */
  public function getStreamChatMessages(): array;

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void;

  /**
   * Get the tools used.
   *
   * @return array
   *   The tools used.
   */
  public function getTools(): array;

  /**
   * Add a callback to run after the stream is finished.
   *
   * @param callable $callback
   *   The callback to run.
   */
  public function addCallback(callable $callback): void;

  /**
   * Create a chat output from the streamed messages.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output.
   */
  public function reconstructChatOutput(): ChatOutput;

  /**
   * Set the finished reason.
   *
   * @param string $finish_reason
   *   The finish reason.
   */
  public function setFinishReason(string $finish_reason): void;

  /**
   * Get the finished reason.
   *
   * @return string|null
   *   The finish reason.
   */
  public function getFinishReason(): string|null;

  /**
   * Gets the original input sent to the provider.
   *
   * @return mixed
   *   The input.
   */
  public function getInput();

  /**
   * Sets the original input sent to the provider.
   *
   * @param mixed $input
   *   The input.
   */
  public function setInput($input): void;

  /**
   * Gets the provider machine name.
   *
   * @return string|null
   *   The provider id.
   */
  public function getProviderId(): ?string;

  /**
   * Sets the provider machine name.
   *
   * @param string $provider_id
   *   The provider id.
   */
  public function setProviderId(string $provider_id): void;

  /**
   * Gets the model id used for the request.
   *
   * @return string|null
   *   The model id.
   */
  public function getModelId(): ?string;

  /**
   * Sets the model id used for the request.
   *
   * @param string $model_id
   *   The model id.
   */
  public function setModelId(string $model_id): void;

  /**
   * Gets the provider configuration array.
   *
   * @return array
   *   The provider configuration.
   */
  public function getProviderConfiguration(): array;

  /**
   * Sets the provider configuration array.
   *
   * @param array $configuration
   *   The provider configuration.
   */
  public function setProviderConfiguration(array $configuration): void;

  /**
   * Gets tags associated with this request/response.
   *
   * @return array
   *   The tags.
   */
  public function getTags(): array;

  /**
   * Sets tags associated with this request/response.
   *
   * @param array $tags
   *   The tags.
   */
  public function setTags(array $tags): void;

  /**
   * Gets the metadata associated with this request/response.
   *
   * @return array
   *   The metadata.
   */
  public function getMetadata(): array;

  /**
   * Sets the metadata associated with this request/response.
   *
   * @param array $metadata
   *   The metadata.
   */
  public function setMetadata(array $metadata): void;

}
