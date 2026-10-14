<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\FunctionalJavascriptTests;

/**
 * Provides helpers for interacting with the DeepChat web component in tests.
 */
trait DeepChatTestTrait {

  /**
   * Waits for the deep-chat custom element to be present and upgraded.
   *
   * The element only exposes its public JS API (submitUserMessage(),
   * getMessages(), ...) once the custom element definition from the bundled
   * deep-chat library has loaded and upgraded the tag.
   */
  protected function waitForDeepChatReady(): void {
    $this->assertSession()->waitForElement('css', 'deep-chat');
    $ready = $this->getSession()->wait(10000, "typeof document.querySelector('deep-chat').submitUserMessage === 'function'");
    $this->assertTrue($ready, 'The deep-chat element upgraded and exposed its API within the timeout.');
  }

  /**
   * Submits a user message through deep-chat's public JS API.
   *
   * @param string $text
   *   The message text to submit.
   */
  protected function submitDeepChatMessage(string $text): void {
    $encoded = json_encode(['text' => $text], JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript("document.querySelector('deep-chat').submitUserMessage($encoded);");
  }

}
