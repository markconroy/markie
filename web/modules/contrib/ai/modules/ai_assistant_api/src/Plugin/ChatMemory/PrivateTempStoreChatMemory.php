<?php

namespace Drupal\ai_assistant_api\Plugin\ChatMemory;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatMemory;

/**
 * Chat memory with one persistent thread per user or session.
 *
 * See TempStoreChatMemoryBase for the storage mechanics. This plugin's only
 * distinguishing trait is hasPersistentThread(): callers can always resolve
 * the same thread for a given owner without one being supplied first, so
 * there is exactly one ongoing conversation per user per assistant.
 */
#[ChatMemory(
  id: 'private_tempstore',
  label: new TranslatableMarkup('Temporary private storage'),
  description: new TranslatableMarkup('Stores the chat history per user or session in expirable temporary private storage. Threads expire automatically. Reuses the same thread every time, so there is only ever one ongoing conversation per user.'),
)]
final class PrivateTempStoreChatMemory extends TempStoreChatMemoryBase {

  /**
   * {@inheritdoc}
   */
  public function hasPersistentThread(): bool {
    return TRUE;
  }

}
