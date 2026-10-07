<?php

declare(strict_types=1);

namespace Drupal\ai_assistant_api;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface;

/**
 * Provides an interface defining an ai assistant entity type.
 */
interface AiAssistantInterface extends ConfigEntityInterface {

  /**
   * Returns the chat memory plugin instance.
   *
   * @return \Drupal\ai\Plugin\ChatMemory\ChatMemoryInterface|null
   *   The chat memory plugin instance, or NULL if none is selected.
   */
  public function getChatMemory(): ?ChatMemoryInterface;

}
