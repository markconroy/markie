<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the DeepChat block when no chat executor is configured.
 *
 * Replaces the removed DeepChatBlockNoAssistantTest: ai_chatbot now ships the
 * AI Assistant API executor, so the block form always lists at least one chat
 * executor, and the front page must keep loading when the block has no
 * executor configured.
 *
 * @group ai_chatbot
 * @group 3585077
 */
#[RunTestsInSeparateProcesses]
class DeepChatNoChatProcessorTest extends BrowserTestBase {

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
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * We need to fix the automators schema.
   *
   * @var bool
   */
  // phpcs:ignore
  protected $strictConfigSchema = FALSE;

  /**
   * Tests the block form lists the built-in AI Assistant API executor.
   */
  public function testNoAvailableChatExecutors(): void {
    $user = $this->drupalCreateUser([
      'administer blocks',
      'access content',
    ]);
    assert($user !== FALSE);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/block/add/ai_deepchat_block/' . $this->defaultTheme);
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->fieldExists('settings[chat_processor_plugin]');
    // The AI Assistant API executor shipped with ai_chatbot is always
    // available, so the "no executors" warning is never shown.
    $assert->pageTextNotContains('There are no available Chat Executors.');
    $assert->optionExists('settings[chat_processor_plugin]', 'ai_assistant_api_processor');
    // The plugin configuration fieldset must not be rendered when nothing is
    // selected (empty fieldset fix).
    $assert->pageTextNotContains('Plugin Configuration');
  }

  /**
   * Tests the front page loads when the block has no chat executor configured.
   */
  public function testFrontPageLoadsWithoutChatExecutor(): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Empty Chatbot',
      'chat_processor_plugin' => '',
    ]);

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextNotContains('The website encountered an unexpected error');
  }

}
