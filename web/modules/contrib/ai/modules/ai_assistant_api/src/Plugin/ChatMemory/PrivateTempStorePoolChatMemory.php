<?php

namespace Drupal\ai_assistant_api\Plugin\ChatMemory;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatMemory;

/**
 * Chat memory with several independent, concurrent threads per user.
 *
 * See TempStoreChatMemoryBase for the storage mechanics, which this plugin
 * shares in full with PrivateTempStoreChatMemory. The difference is purely
 * that hasPersistentThread() is FALSE (the ChatMemoryPluginBase default):
 * no single thread id can be resolved for an owner up front, since callers
 * are expected to keep several separate conversations alive (for example
 * AiAssistantApiRunner's rotating pool of thread slots) rather than always
 * continuing the same one.
 */
#[ChatMemory(
  id: 'private_tempstore_pool',
  label: new TranslatableMarkup('Temporary private storage (multiple threads)'),
  description: new TranslatableMarkup('Stores the chat history per user or session in expirable temporary private storage. Threads expire automatically. Unlike the single-thread option, new conversations may start a new thread instead of continuing a previous one.'),
)]
final class PrivateTempStorePoolChatMemory extends TempStoreChatMemoryBase {

}
