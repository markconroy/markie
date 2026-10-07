<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\FunctionalJavascriptTests;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\ai_assistant_api\Entity\AiAssistant;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the DeepChat block's message history follows the ChatMemory.
 *
 * Places the ai_deepchat_block (with its "toolbar" placement style) in the
 * content region of the claro admin theme, visits an admin page as a logged
 * in user, and drives the deep-chat web component's public JS API directly
 * (submitUserMessage()/getMessages()) rather than the shadow DOM it renders
 * into, since that DOM is not reachable through normal Mink/CSS selectors.
 *
 * - private_tempstore always resolves the same thread for a given user (see
 *   AiAssistantApiRunner::generateUniqueKey()), so a message sent before a
 *   reload is still part of the conversation after it.
 * - private_tempstore_pool has no persistent thread pointer: each fresh
 *   request without a client-supplied thread id picks the next free slot in
 *   a rotating pool, so a reload starts a new, empty thread and the message
 *   sent before the reload is gone.
 *
 * @group ai_chatbot
 * @group 3586539
 */
#[RunTestsInSeparateProcesses]
class DeepChatChatMemoryPersistenceTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'key',
    'ai',
    'ai_test',
    'ai_assistant_api',
    'ai_chatbot',
    'toolbar',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_chatbot';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Tests that private_tempstore_pool does not remember messages on reload.
   */
  public function testPrivateTempStorePoolForgetsMessagesOnReload(): void {
    $this->assertChatMemoryBehaviorAcrossReload('private_tempstore_pool', FALSE);
  }

  /**
   * Tests that private_tempstore remembers messages across a reload.
   */
  public function testPrivateTempStoreRemembersMessagesAcrossReload(): void {
    $this->assertChatMemoryBehaviorAcrossReload('private_tempstore', TRUE);
  }

  /**
   * Sends a message through the DeepChat block and checks it after a reload.
   *
   * @param string $chatMemoryPluginId
   *   The ChatMemory plugin id to configure on the test assistant
   *   ('private_tempstore' or 'private_tempstore_pool').
   * @param bool $persistsAcrossReload
   *   Whether the message sent before the reload is expected to still be
   *   part of the conversation after it.
   */
  protected function assertChatMemoryBehaviorAcrossReload(string $chatMemoryPluginId, bool $persistsAcrossReload): void {
    $assistantId = 'chat_memory_test_' . $chatMemoryPluginId;
    AiAssistant::create([
      'id' => $assistantId,
      'label' => 'Chat Memory Test Assistant',
      'description' => '',
      'instructions' => '',
      'allow_history' => $chatMemoryPluginId,
      'chat_memory_settings' => [
        'expiry' => 604800,
        'max_messages' => 0,
      ],
      'error_message' => 'An error occurred.',
      'llm_provider' => 'echoai',
      'llm_model' => 'gpt-test',
      'llm_configuration' => [],
      'specific_error_messages' => [],
    ])->save();

    // Placing the block, claro is the default theme.
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Test DeepChat',
      'chat_processor_plugin' => 'ai_assistant_api_processor',
      'plugin_configuration' => [
        'assistant_id' => $assistantId,
        'stream_output' => FALSE,
        'verbose_mode' => FALSE,
        'show_structured_results' => FALSE,
      ],
      'placement' => 'toolbar',
    ]);

    // /admin's own access check (SystemAdminMenuBlockAccessCheck) requires
    // the user to have access to at least one *directly* accessible child
    // link in the admin menu tree (recursively), not just 'access
    // administration pages' by itself. 'administer blocks' unlocks the
    // Structure > Block layout page, which satisfies that and doubles as a
    // realistic permission for someone managing this chatbot block.
    $admin = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'access toolbar',
      'access deepchat api',
    ]);
    $this->drupalLogin($admin);

    $this->drupalGet('admin');
    $this->waitForDeepChatReady();
    $this->openDeepChatWindow();
    $this->takeScreenshot($chatMemoryPluginId . '_1_loaded');

    $marker = 'Persistence check ' . $chatMemoryPluginId . ' ' . $this->randomMachineName(8);
    $this->submitDeepChatMessage($marker);
    $this->waitForDeepChatReply($marker);
    $this->takeScreenshot($chatMemoryPluginId . '_2_replied');

    $this->getSession()->reload();
    $this->waitForDeepChatReady();
    $this->openDeepChatWindow();
    $this->takeScreenshot($chatMemoryPluginId . '_3_reloaded');

    $messagesAfterReload = $this->getDeepChatMessagesJson();
    if ($persistsAcrossReload) {
      $this->assertStringContainsString($marker, $messagesAfterReload, 'The message sent before the reload is still present after it.');
    }
    else {
      $this->assertStringNotContainsString($marker, $messagesAfterReload, 'The message sent before the reload is gone after it.');
    }
  }

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
   * Opens the DeepChat "toolbar" placement window so it is visible.
   *
   * With the 'toolbar' placement, the block is rendered fixed off-canvas
   * (width: 0, opacity: 0, pointer-events: none) until the body carries the
   * 'ai-chatbot-opened' class (see css/toolbar-chatbot.css). In production
   * that class is toggled by clicking the toolbar tray button that
   * ChatbotHooks::toolbar() (hook_toolbar()) adds to the core toolbar, once
   * js/toolbar-chatbot.js removes its initial 'hidden' class. This clicks
   * that real button rather than poking the CSS class directly, so the
   * screenshots/video show the same interaction a real user performs.
   *
   * The toggle_state 'remember' default persists the open/closed state in
   * localStorage (js/toolbar-chatbot.js re-opens automatically on load if
   * it was left open), so this only clicks the tab when the panel isn't
   * already open: clicking it while it's already open would toggle it
   * closed instead, and on a page that already auto-opened it the click
   * target itself is covered by the now-visible panel.
   */
  protected function openDeepChatWindow(): void {
    $isOpen = (bool) $this->getSession()->evaluateScript("document.body.classList.contains('ai-chatbot-opened')");
    if (!$isOpen) {
      $button = $this->assertSession()->waitForElementVisible('css', '.toolbar-icon-ai-chatbot:not(.hidden)');
      $this->assertNotNull($button, 'The chatbot toolbar tab became visible.');
      $button->click();
    }
    $this->assertSession()->waitForElementVisible('css', '.block-ai-deepchat-block');
  }

  /**
   * Submits a user message through deep-chat's public JS API.
   *
   * The widget's input lives in a shadow root, so this goes through
   * deep-chat's own submitUserMessage() method instead of a real DOM click
   * and keypresses.
   *
   * @param string $text
   *   The message text to submit.
   */
  protected function submitDeepChatMessage(string $text): void {
    $encoded = json_encode(['text' => $text], JSON_THROW_ON_ERROR);
    $this->getSession()->executeScript("document.querySelector('deep-chat').submitUserMessage($encoded);");
  }

  /**
   * Waits until the assistant's reply (echoing the marker) has arrived.
   *
   * @param string $marker
   *   The unique text sent as the user message, which the echoai provider
   *   embeds back in its reply.
   */
  protected function waitForDeepChatReply(string $marker): void {
    $encodedMarker = json_encode($marker, JSON_THROW_ON_ERROR);
    $condition = "JSON.stringify(document.querySelector('deep-chat').getMessages() || []).includes($encodedMarker) "
      . "&& (document.querySelector('deep-chat').getMessages() || []).length >= 2";
    $arrived = $this->getSession()->wait(15000, $condition);
    $this->assertTrue($arrived, 'The assistant responded within the timeout.');
  }

  /**
   * Returns the current deep-chat conversation as a JSON string.
   *
   * @return string
   *   The JSON-encoded messages array (or '[]' if not yet ready).
   */
  protected function getDeepChatMessagesJson(): string {
    return (string) $this->getSession()->evaluateScript(
      "JSON.stringify(document.querySelector('deep-chat').getMessages())"
    );
  }

}
