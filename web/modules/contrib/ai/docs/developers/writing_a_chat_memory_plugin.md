# Writing a ChatMemory Plugin

The AI module provides a plugin system for storing chatbot conversation history through ChatMemory plugins. This allows chatbots to persist the chat history server-side across requests, so a conversation survives page reloads and can be resumed later.

## Overview

Chat memory is opt-in: **each chatbot remains responsible for its own memory handling**. A chatbot can:

- Use a ChatMemory plugin to persist history server-side, or
- Handle history entirely client-side (for example in the browser via `localStorage` or the state of the chat widget), or
- Use no history at all.

Messages are grouped in **threads**, where a thread is one conversation identified by an opaque thread id string. The ChatMemory plugin stores and retrieves the messages of a thread; how the chatbot maps users or sessions to a thread id is up to the chatbot.

ChatMemory is distinct from the `AiShortTermMemory` plugin type: short term memory *transforms* an in-flight chat history right before an AI call (compaction, summarization etc.) and owns no storage, while ChatMemory is the *persistence layer* for chat history. The two compose well: a chatbot can load a long history from a ChatMemory plugin and let a short term memory plugin compact it before the AI request.

The plugin system is built on [Drupal's plugin system](https://api.drupal.org/api/drupal/core%21core.api.php/group/plugin_api) and uses PHP 8 attributes for plugin discovery.

## Security: thread ownership

Thread ids are usually round-tripped via the client. Implementations are responsible for scoping threads to the current user or session, so one user can never read another user's conversation:

- `hasThread()` must only return `TRUE` for threads the current user is allowed to access.
- Storage should be inherently scoped per user or session (like the private temp store), or the implementation must do its own access checks on every operation.

Chatbots can go one step further and keep the current thread id in the server-side session, so the client never chooses which thread is written to.

## Creating a ChatMemory Plugin

### 1. Create the Plugin Class

Create a new PHP class in `src/Plugin/ChatMemory/` that extends `ChatMemoryPluginBase`:

```php
<?php

namespace Drupal\your_module\Plugin\ChatMemory;

use Drupal\ai\Attribute\ChatMemory;
use Drupal\ai\Base\ChatMemoryPluginBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[ChatMemory(
  id: 'your_plugin_id',
  label: new TranslatableMarkup('Your Plugin Label'),
  description: new TranslatableMarkup('Description of where the history is stored.')
)]
class YourPlugin extends ChatMemoryPluginBase {

  /**
   * {@inheritdoc}
   */
  public function createThreadId(): string {
    return bin2hex(random_bytes(16));
  }

  /**
   * {@inheritdoc}
   */
  public function hasThread(string $thread_id): bool {
    // Only return TRUE if the thread exists AND the current user may
    // access it.
  }

  /**
   * {@inheritdoc}
   */
  public function loadMessages(string $thread_id): array {
    // Return an array of ChatMessage objects, oldest first.
  }

  /**
   * {@inheritdoc}
   */
  public function saveMessages(string $thread_id, array $messages): void {
    // Store the full list of ChatMessage objects, replacing any existing
    // messages of the thread.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteThread(string $thread_id): void {
    // Remove the thread including messages and metadata.
  }

}
```

### 2. Required Methods

All ChatMemory plugins must implement:

- `createThreadId()`: Returns a new unique thread id
- `hasThread()`: Whether a thread exists and is accessible by the current user
- `loadMessages()`: Load all messages of a thread as `ChatMessage[]`
- `saveMessages()`: Store the full message list of a thread (overwrite)
- `deleteThread()`: Remove a thread entirely

The `ChatMemoryPluginBase` class provides default implementations for:

- `appendMessages()`: Appends messages using `loadMessages()` + `saveMessages()`. Override it if your storage backend has a cheaper native append.
- `clearMessages()`: Empties a thread via `saveMessages($thread_id, [])`, keeping the thread id valid.
- `hasPersistentThread()`: Defaults to `FALSE` - threads only become known once a caller has created or been given one (for example a pool of several concurrent conversations). Override it to return `TRUE` if instead a thread id can always be resolved for the current owner without one being supplied first (a single, ever-reused conversation). Consumers use this to decide things like whether history can be shown on a plain page load, without hardcoding which plugin is configured.
- The `ConfigurableInterface` and `PluginFormInterface` boilerplate. Override `defaultConfiguration()` and `buildConfigurationForm()` to make your plugin configurable, for example for expiry times or message limits.

### 3. Serializing Messages

Use `ChatMessage::toArray()` and `ChatMessage::fromArray()` to serialize messages for storage — do not invent your own message serialization:

```php
$stored = array_map(fn (ChatMessage $message) => $message->toArray(), $messages);
$messages = array_map(fn (array $data) => ChatMessage::fromArray($data), $stored);
```

## Built-in Plugins

- **`private_tempstore` (Temporary storage):** Ships with the AI Assistant API module. Stores threads in expirable temporary storage, scoped per user (or anonymous session, the same way the private temp store scopes its data), with a configurable expiry and an optional maximum number of messages per thread. `hasPersistentThread()` is `TRUE`: there is one ongoing conversation per user.
- **`private_tempstore_pool` (Temporary storage, multiple threads):** Ships alongside `private_tempstore` and shares its storage implementation entirely. The only difference is `hasPersistentThread()` is `FALSE`, signalling that a caller may keep several independent, concurrent threads per owner instead of always continuing the same one.
- **`state_memory` (State Memory):** A test plugin in the `ai_test` module, storing threads in state without access scoping. Only for use in tests.

## Using ChatMemory Plugins

### Programmatic Usage

```php
// Get the plugin manager.
$manager = \Drupal::service('plugin.manager.ai.chat_memory');

// Get available plugins.
$plugins = $manager->getDefinitions();

// Create a plugin instance.
$memory = $manager->createInstance('private_tempstore', [
  'expiry' => 86400,
  'max_messages' => 50,
]);

// Start a conversation.
$thread_id = $memory->createThreadId();

// Append the user message and the assistant response after each exchange.
$memory->appendMessages($thread_id, [
  new ChatMessage('user', 'Hello!'),
  new ChatMessage('assistant', 'Hi, how can I help?'),
]);

// On the next request, restore the conversation.
$history = $memory->loadMessages($thread_id);

// Reset the conversation.
$memory->deleteThread($thread_id);
```

### How a chatbot opts in

Using a ChatMemory plugin is entirely opt-in and the chatbot stays in charge of its own memory handling. A typical server-side integration:

1. Offer a **Chat Memory** setting listing all ChatMemory plugins, defaulting to *None (client-side history)*. Without a memory plugin, history handling stays exactly as it is today.
2. When a memory plugin is selected, keep the current thread id in the user's session, load the stored history server-side for each call, only take the *latest* user message from the client, and append the new user message and assistant response to the thread after each exchange.
3. On page load, preload the stored history into the chat widget, and let the reset button delete the thread and start a new one.

A chatbot that prefers frontend-only history simply does not select a memory plugin and keeps managing its messages client-side.

## Architecture

### Class Hierarchy

```
ChatMemoryInterface (interface)
  ├── Extends: PluginFormInterface
  ├── Extends: ConfigurableInterface
  └── ChatMemoryPluginBase (abstract base class)
      ├── Extends: PluginBase
      └── Your Plugin Implementation
```

### Plugin Discovery

The `ChatMemoryPluginManager` uses PHP 8 attribute-based discovery to find plugins across all modules:

- **Namespace:** `Plugin/ChatMemory`
- **Attribute:** `Drupal\ai\Attribute\ChatMemory`
- **Interface:** `Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface`
- **Service:** `plugin.manager.ai.chat_memory`
- **Alter hook:** `hook_chat_memory_info_alter()`

## Troubleshooting

### Plugin Not Found
- Ensure your plugin class is in the correct namespace: `Drupal\your_module\Plugin\ChatMemory`
- Check that the `#[ChatMemory]` attribute is properly defined
- Clear the plugin cache: `drush cache:rebuild`

### History Not Persisting
- Verify the chatbot actually has a memory plugin configured
- Check the thread expiry configuration — expired threads are treated as gone
- For anonymous users, storage backends based on the private temp store need a session

## Best Practices

1. **Scope threads to the user:** Never let one user read another user's thread
2. **Use Dependency Injection:** Inject services through the constructor via `ContainerFactoryPluginInterface`
3. **Use `ChatMessage::toArray()`/`fromArray()`:** Do not invent your own message serialization
4. **Expire old threads:** Chat history can contain personal data; do not keep it forever
5. **Implement Configuration:** Provide sensible defaults, for example for expiry and message limits
6. **Do not touch the session in write paths:** With streamed responses, chatbots append messages after the response has started, when the session has already been closed. Resolve any session-derived state (like an anonymous owner key) on first use and cache it on the plugin instance — see `PrivateTempStoreChatMemory::getOwner()` for the pattern.

## See Also

- [ChatProcessor Plugin](writing_a_chat_processor_plugin.md) - Processing chat input into responses
- [Chat Operations](call_chat.md) - Working with chat operations

**This document was generated with AI assistance.**
