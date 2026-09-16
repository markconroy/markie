<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the toolbar chatbot gate honours placement, access and cache tags.
 *
 * The toolbar button, toolbar libraries and the early state-restoration
 * script must only be delivered when an accessible chatbot block is
 * configured with the toolbar placement, and the decision must be cached
 * with metadata that invalidates when the block configuration changes.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
class ToolbarChatbotGateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'block',
    'toolbar',
    'dynamic_page_cache',
    'ai',
    'ai_test',
    'ai_assistant_api',
    'ai_chatbot',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user allowed to use the chat API and see the toolbar.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $chatUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A working assistant: an explicit provider/model pair means
    // AiAssistantApiRunner::isSetup() passes without any live provider call,
    // and the authenticated role satisfies its role-based access check.
    $this->container->get('entity_type.manager')
      ->getStorage('ai_assistant')
      ->create([
        'id' => 'test_assistant',
        'label' => 'Test assistant',
        'description' => 'Assistant for the toolbar gate test.',
        'allow_history' => 'session',
        'system_role' => 'You are a test assistant.',
        'preprompt_instructions' => '',
        'assistant_message' => '',
        'error_message' => 'Something went wrong.',
        'llm_provider' => 'echoai',
        'llm_model' => 'default',
        'llm_configuration' => [],
        'specific_error_messages' => [],
        'roles' => ['authenticated' => 'authenticated'],
      ])
      ->save();

    $this->chatUser = $this->drupalCreateUser([
      'access deepchat api',
      'access toolbar',
    ]);
  }

  /**
   * Tests delivery follows the block placement, including on config change.
   */
  public function testToolbarDeliveryFollowsBlockPlacement(): void {
    $block = $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Toolbar gate test chatbot',
      'ai_assistant' => 'test_assistant',
      'placement' => 'toolbar',
    ]);

    // With a toolbar-placed accessible block, the early script, the toolbar
    // library and the toolbar button are all delivered.
    $this->drupalLogin($this->chatUser);
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('js/toolbar-chatbot-early.js');
    $this->assertSession()->responseContains('button--ai-chatbot');

    // Request the page again so it is served from the dynamic page cache
    // before the configuration changes; a wrong or hand-built cache tag
    // would keep serving the toolbar assets after the change below.
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('js/toolbar-chatbot-early.js');

    // Move the block away from the toolbar placement.
    $block->set('settings', ['placement' => 'bottom_right'] + $block->get('settings'))->save();

    // Toolbar-specific delivery must stop, and must stop despite the cached
    // page: the block config change has to invalidate the cached response.
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('js/toolbar-chatbot-early.js');
    $this->assertSession()->responseNotContains('button--ai-chatbot');

    // Moving it back restores delivery.
    $block->set('settings', ['placement' => 'toolbar'] + $block->get('settings'))->save();
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('js/toolbar-chatbot-early.js');
  }

  /**
   * Tests a disabled block stops toolbar delivery.
   */
  public function testDisabledBlockStopsDelivery(): void {
    $block = $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'ai_assistant' => 'test_assistant',
      'placement' => 'toolbar',
    ]);

    $this->drupalLogin($this->chatUser);
    $this->drupalGet('<front>');
    $this->assertSession()->responseContains('js/toolbar-chatbot-early.js');

    $block->disable()->save();
    $this->drupalGet('<front>');
    $this->assertSession()->responseNotContains('js/toolbar-chatbot-early.js');
    $this->assertSession()->responseNotContains('button--ai-chatbot');
  }

  /**
   * Tests users without the chat permission get no toolbar assets.
   */
  public function testNoPermissionNoDelivery(): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'ai_assistant' => 'test_assistant',
      'placement' => 'toolbar',
    ]);

    $viewer = $this->drupalCreateUser(['access toolbar']);
    $this->drupalLogin($viewer);
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('js/toolbar-chatbot-early.js');
    $this->assertSession()->responseNotContains('button--ai-chatbot');
  }

  /**
   * Tests a block whose assistant is missing does not deliver toolbar assets.
   */
  public function testInaccessibleBlockStopsDelivery(): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'ai_assistant' => '',
      'placement' => 'toolbar',
    ]);

    $this->drupalLogin($this->chatUser);
    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseNotContains('js/toolbar-chatbot-early.js');
    $this->assertSession()->responseNotContains('button--ai-chatbot');
  }

}
