<?php

namespace Drupal\ai\Plugin\ChatProcessor;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatOutput;

/**
 * Defines an interface for ChatProcessor plugins.
 *
 * ChatProcessor plugins provide different ways to process chat input and
 * generate responses. They can be used to implement various chatbot
 * behaviors like RAG, tool use, or custom processing logic.
 *
 * This interface should be implemented by extending ChatProcessorBase, which
 * provides backwards-compatible defaults when methods are added.
 */
interface ChatProcessorInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Sets the input to use for execution.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The chat input object.
   */
  public function setInput(ChatInput $input): void;

  /**
   * Gets the input to use for execution.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatInput|null
   *   The chat input object, or NULL if not set.
   */
  public function getInput(): ?ChatInput;

  /**
   * Sets the output to use for execution.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $output
   *   The chat output object.
   */
  public function setOutput(ChatOutput $output): void;

  /**
   * Gets the output to use for execution.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput|null
   *   The chat output object, or NULL if not set.
   */
  public function getOutput(): ?ChatOutput;

  /**
   * Executes the plugin.
   *
   * This is the main method that performs the actual processing logic.
   * Implementations should process the input and generate appropriate output.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The processed chat output.
   *
   * @throws \Exception
   *   If execution fails.
   */
  public function doExecute(): ChatOutput;

  /**
   * Executes the plugin with state management.
   *
   * Validates input, calls doExecute(), and stores the output.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The processed chat output.
   *
   * @throws \Exception
   *   If execution fails.
   */
  public function execute(): ChatOutput;

  /**
   * Sets the thread ID for the current process.
   *
   * @param string $threadId
   *   The thread ID.
   */
  public function setThreadId(string $threadId): void;

  /**
   * Gets the thread ID for the current process.
   *
   * @return string|null
   *   The thread ID, or NULL if not set.
   */
  public function getThreadId(): ?string;

  /**
   * Sets whether the execution is finished.
   *
   * @param bool $finished
   *   TRUE if execution is finished, FALSE if the chatbot should continue
   *   polling for progress. Defaults to TRUE. Only set to FALSE for looping
   *   agents where each step should be called via the chatbot to see progress.
   */
  public function setFinished(bool $finished): void;

  /**
   * Gets whether the execution is finished.
   *
   * @return bool
   *   TRUE if execution is finished, FALSE if the chatbot should continue
   *   polling for progress.
   */
  public function getFinished(): bool;

  /**
   * Sets the files that are not images to the input.
   *
   * Images should be set in ChatInput. This is for other file types like
   * PDFs, documents, etc.
   *
   * @param \Drupal\ai\OperationType\GenericType\GenericFile[] $files
   *   An array of file objects.
   */
  public function setInputFiles(array $files): void;

  /**
   * Gets the files that are not images from the input.
   *
   * @return array
   *   An array of file objects.
   */
  public function getInputFiles(): array;

  /**
   * Returns the allowed file extensions that this consumer can handle.
   *
   * @return array
   *   An array of file extensions (e.g., ['pdf', 'doc', 'txt']).
   *   Empty array means no non-image files are allowed.
   */
  public function allowedFileExtensions(): array;

  /**
   * Returns whether this consumer allows image files in ChatInput.
   *
   * @return bool
   *   TRUE if images are allowed, FALSE otherwise.
   */
  public function allowsImages(): bool;

  /**
   * Reset a whole thread and get a new thread id.
   *
   * @param string $thread_id
   *   The thread id to reset.
   *
   * @return string
   *   The new thread id.
   */
  public function resetThread($thread_id): string;

  /**
   * Checks if the given account may execute this processor instance.
   *
   * Each plugin decides its own rules based on its configuration (e.g. the
   * AI Assistant processor checks the roles configured on the assistant
   * entity). Callers must check this before execute() and should add the
   * result to their cacheability.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResultInterface;

  /**
   * Gets the stored conversation history for the current thread.
   *
   * Requires the thread id to be set. Plugins without server-side history
   * return an empty array. Plugins may truncate the returned history (the
   * AI Assistant runner slices it to its configured context length).
   *
   * @return array<int, array{role: string, message: string, timestamp?: int}>
   *   The messages in chronological order.
   */
  public function getMessageHistory(): array;

  /**
   * Notifies the plugin that a streamed response has fully rendered.
   *
   * Called by the consumer once all chunks have been sent, with the full
   * response text, so plugins that keep server-side history can persist the
   * assistant reply. No-op for stateless plugins.
   *
   * @param string $message
   *   The complete streamed response text.
   */
  public function onStreamComplete(string $message): void;

  /**
   * Gets trusted markup to append after the response has been sanitized.
   *
   * Called by the consumer after execution, once the LLM text has been
   * rendered and filtered. The returned markup is appended verbatim, so it
   * must be plugin-generated and safe (escape any embedded model or user
   * content). Used e.g. for the structured results dump of the AI Assistant
   * processor.
   *
   * @return string
   *   The markup to append, or an empty string.
   */
  public function getPostResponseMarkup(): string;

}
