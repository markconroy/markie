<?php

namespace Drupal\ai\Plugin\ChatMemory;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for ChatMemory plugins.
 *
 * ChatMemory plugins provide server-side storage of chat history for
 * chatbots. Messages are grouped in threads, where a thread is one
 * conversation identified by a thread id. Chatbots can opt into using a
 * ChatMemory plugin to persist conversation history across requests, or
 * handle history on their own, for example client-side in the browser.
 *
 * Since thread ids are usually round-tripped via the client, implementations
 * are responsible for scoping threads to the current user or session, so
 * that one user can never read another user's conversation. hasThread()
 * should only return TRUE for threads the current user is allowed to access.
 *
 * This is distinct from the AiShortTermMemory plugin type: short term memory
 * transforms an in-flight chat history right before an AI call (compaction,
 * summarization etc.) and owns no storage, while ChatMemory is the
 * persistence layer for chat history.
 */
interface ChatMemoryInterface extends PluginFormInterface, ConfigurableInterface {

  /**
   * Creates a new unique thread id for the current user or session.
   *
   * @return string
   *   The new thread id.
   */
  public function createThreadId(): string;

  /**
   * Checks that a thread exists and the current user may access it.
   *
   * @param string $thread_id
   *   The thread id.
   *
   * @return bool
   *   TRUE if the thread exists and is accessible by the current user.
   */
  public function hasThread(string $thread_id): bool;

  /**
   * Loads all messages of a thread.
   *
   * @param string $thread_id
   *   The thread id.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The messages of the thread, oldest first. Empty array if the thread
   *   does not exist or has no messages.
   */
  public function loadMessages(string $thread_id): array;

  /**
   * Saves the messages of a thread, replacing any existing messages.
   *
   * @param string $thread_id
   *   The thread id.
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   The full list of messages to store, oldest first.
   */
  public function saveMessages(string $thread_id, array $messages): void;

  /**
   * Appends messages to the end of a thread.
   *
   * @param string $thread_id
   *   The thread id.
   * @param \Drupal\ai\OperationType\Chat\ChatMessage[] $messages
   *   One or more messages to append.
   */
  public function appendMessages(string $thread_id, array $messages): void;

  /**
   * Removes all messages of a thread, keeping the thread id valid.
   *
   * @param string $thread_id
   *   The thread id.
   */
  public function clearMessages(string $thread_id): void;

  /**
   * Deletes a thread including its messages and metadata.
   *
   * @param string $thread_id
   *   The thread id.
   */
  public function deleteThread(string $thread_id): void;

  /**
   * Whether this plugin exposes one persistent thread per owner.
   *
   * TRUE means a thread id can be resolved for the current user or session
   * without one being supplied first - for example a single, ongoing
   * conversation that is always reused. Consumers use this to decide
   * whether prior history can be shown on a plain page load and whether a
   * "clear history" action makes sense, without needing to know which
   * plugin is configured. FALSE (the default) means threads only become
   * known once a caller has actually created or been given one, as with a
   * pool of several concurrent, independent conversations.
   *
   * @return bool
   *   TRUE if a persistent thread exists per owner, FALSE otherwise.
   */
  public function hasPersistentThread(): bool;

}
