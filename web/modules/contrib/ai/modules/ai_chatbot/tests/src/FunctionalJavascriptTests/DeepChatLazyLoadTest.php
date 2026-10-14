<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\FunctionalJavascriptTests;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the interaction strategy really defers the Deep Chat bundle.
 *
 * The markup cannot tell the idle and interaction strategies apart, so this
 * drives a real browser and asserts on resource timings instead:
 *
 * - nothing is requested while the visitor ignores the chat,
 * - focusing the launcher preloads the bundle without executing it, which is
 *   the whole point of <link rel="modulepreload">: the request shows up while
 *   the custom element stays undefined,
 * - opening the chat is what executes it and upgrades <deep-chat>.
 *
 * Both placements are covered because they signal "opened" differently, and
 * the loader in js/deepchat-init.js has to watch the right one for each: the
 * toolbar toggles ai-chatbot-opened on <body> and is launched from the admin
 * toolbar, while the sticky placements toggle chat-open on the container
 * itself and are launched from their own header. Watching only the container
 * would leave the toolbar placement, which is the default, waiting forever.
 *
 * @group ai_chatbot
 * @group 3586671
 */
#[RunTestsInSeparateProcesses]
class DeepChatLazyLoadTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'ai',
    'ai_assistant_api',
    'ai_chatbot',
    'ai_chatbot_test',
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
   * Tests the toolbar placement, which opens by a class on <body>.
   */
  public function testToolbarPlacementDefersTheBundle(): void {
    $this->assertBundleIsDeferredUntilOpened(
      'toolbar',
      '.toolbar-icon-ai-chatbot:not(.hidden)',
      "document.body.classList.contains('ai-chatbot-opened')",
    );
  }

  /**
   * Tests a sticky placement, which opens by a class on the container.
   */
  public function testStickyPlacementDefersTheBundle(): void {
    $this->assertBundleIsDeferredUntilOpened(
      'bottom-right',
      '.ai-deepchat--header',
      "document.querySelector('.chat-container').classList.contains('chat-open')",
    );
  }

  /**
   * Asserts the bundle is only fetched on intent and only run on open.
   *
   * @param string $placement
   *   The block placement to test.
   * @param string $launcher
   *   CSS selector of the control that opens the chat for that placement.
   * @param string $open_condition
   *   JavaScript expression that is true once the chat is open.
   */
  protected function assertBundleIsDeferredUntilOpened(string $placement, string $launcher, string $open_condition): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Test DeepChat',
      'chat_processor_plugin' => 'chatbot_test_processor',
      'placement' => $placement,
      'load_strategy' => 'interaction',
    ]);

    // The toolbar tab is only added for users who can reach the API, and
    // /admin needs a reachable child link to grant access at all.
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer blocks',
      'access toolbar',
      'access deepchat api',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin');
    $this->assertSession()->waitForElement('css', 'deep-chat');

    // Nothing at all while the chat is ignored.
    $this->assertSame(0, $this->bundleRequests(), 'The bundle is not requested before any interaction.');
    $this->assertFalse($this->deepChatIsDefined());

    // Showing intent preloads it. focusin is used rather than a hover so the
    // assertion does not depend on synthetic mouse movement, and it is the
    // path keyboard and screen reader users take anyway. The event is
    // dispatched rather than produced by focusing the element: CI runs
    // FunctionalJavascript tests in parallel browser windows on one display,
    // and Chrome does not fire focus events in a window that is not in front.
    $control = $this->assertSession()->waitForElementVisible('css', $launcher);
    $this->assertNotNull($control, sprintf('The launcher %s became visible.', $launcher));
    $this->getSession()->executeScript(sprintf(
      "document.querySelector(%s).dispatchEvent(new FocusEvent('focusin', {bubbles: true}));",
      json_encode($launcher),
    ));
    $this->assertJsCondition("performance.getEntriesByType('resource').filter((e) => e.name.includes('deepchat.bundle')).length > 0");

    // Downloaded, but deliberately not executed yet.
    $this->assertFalse($this->deepChatIsDefined(), 'The preload does not execute the bundle.');
    $this->assertFalse((bool) $this->getSession()->evaluateScript($open_condition));

    // Opening the chat is what executes it.
    $control->click();
    $this->assertJsCondition($open_condition);
    $this->assertJsCondition("!!customElements.get('deep-chat')");
  }

  /**
   * Counts the requests made for the Deep Chat bundle.
   *
   * @return int
   *   The number of matching resource timings.
   */
  protected function bundleRequests(): int {
    return (int) $this->getSession()->evaluateScript("performance.getEntriesByType('resource').filter((e) => e.name.includes('deepchat.bundle')).length");
  }

  /**
   * Whether the browser has registered the <deep-chat> custom element.
   *
   * @return bool
   *   TRUE once the bundle has been executed.
   */
  protected function deepChatIsDefined(): bool {
    return (bool) $this->getSession()->evaluateScript("!!customElements.get('deep-chat')");
  }

}
