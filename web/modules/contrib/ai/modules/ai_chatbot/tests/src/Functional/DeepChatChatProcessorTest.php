<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ChatProcessor selection flow on the DeepChat block.
 *
 * Covers the behavior introduced when the chatbot moved from the AI Assistant
 * selection to the ChatProcessor plugin system: listing the available chat
 * executors, the empty fieldset fix and persisting the selected executor.
 *
 * @group ai_chatbot
 * @group 3585077
 */
#[RunTestsInSeparateProcesses]
class DeepChatChatProcessorTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $user = $this->drupalCreateUser([
      'administer blocks',
      'access content',
    ]);
    assert($user !== FALSE);
    $this->drupalLogin($user);
  }

  /**
   * The block add form path for the DeepChat block.
   *
   * @return string
   *   The route path.
   */
  protected function blockAddUrl(): string {
    return 'admin/structure/block/add/ai_deepchat_block/' . $this->defaultTheme;
  }

  /**
   * Tests selecting a chat executor, persisting it and the config fieldset.
   */
  public function testChatExecutorSelectionFlow(): void {
    $this->drupalGet($this->blockAddUrl());
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);

    // The available executor (provided by the test fixture module) is listed
    // and the "no executors" warning is not shown.
    $assert->pageTextNotContains('There are no available Chat Executors.');
    $assert->pageTextContains('The following chat executors are available');
    $assert->optionExists('settings[chat_processor_plugin]', 'chatbot_test_processor');
    // No plugin configuration fieldset until an executor is selected (empty
    // fieldset fix).
    $assert->pageTextNotContains('Plugin Configuration');

    // Save the block with a chat executor selected.
    $this->submitForm([
      'settings[label]' => 'Test Chatbot',
      'settings[chat_processor_plugin]' => 'chatbot_test_processor',
      'region' => 'content',
    ], 'Save block');
    $assert->statusCodeEquals(200);

    // The selected chat executor is persisted on the block configuration.
    $blocks = \Drupal::entityTypeManager()
      ->getStorage('block')
      ->loadByProperties(['plugin' => 'ai_deepchat_block']);
    $this->assertNotEmpty($blocks, 'The DeepChat block was created.');
    $block = reset($blocks);
    $settings = $block->get('settings');
    $this->assertSame('chatbot_test_processor', $settings['chat_processor_plugin']);

    // Re-opening the block shows the plugin configuration fieldset with the
    // selected executor's configuration form.
    $this->drupalGet('admin/structure/block/manage/' . $block->id());
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Plugin Configuration');
    $assert->fieldExists('settings[plugin_configuration][greeting]');
  }

  /**
   * Tests the front page loads with the block and a chat executor configured.
   */
  public function testFrontPageLoadsWithChatExecutor(): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Front Chatbot',
      'chat_processor_plugin' => 'chatbot_test_processor',
    ]);

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextNotContains('The website encountered an unexpected error');
  }

  /**
   * Tests the dialog library is left out when structured results are off.
   */
  public function testDialogLibraryOmittedWithoutStructuredResults(): void {
    $this->config('system.performance')->set('js.preprocess', FALSE)->save();
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Front Chatbot',
      'chat_processor_plugin' => 'chatbot_test_processor',
    ]);

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->responseContains('deepchat-init.js');
    $assert->responseNotContains('dialog.ajax.js');
    $assert->responseNotContains('dialog.jquery-ui.js');
  }

  /**
   * Tests the dialog library is attached when structured results are on.
   */
  public function testDialogLibraryAttachedForStructuredResults(): void {
    $this->config('system.performance')->set('js.preprocess', FALSE)->save();
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Front Chatbot',
      'chat_processor_plugin' => 'chatbot_test_processor',
      'plugin_configuration' => ['show_structured_results' => TRUE],
    ]);

    $this->drupalGet('<front>');
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    // Deep Chat opens the modal with Drupal.dialog().showModal(), so only the
    // core/drupal.dialog stack is needed. The heavier core/drupal.dialog.ajax
    // variant, which pulls in core/drupal.ajax, must stay out.
    $assert->responseContains('dialog.jquery-ui.js');
    $assert->responseNotContains('dialog.ajax.js');
  }

  /**
   * Tests which strategies attach the bundle and which defer it to the loader.
   *
   * Only the eager strategy attaches it server side. idle and interaction are
   * indistinguishable in the markup: what separates them lives in the loader
   * in js/deepchat-init.js, so it is covered by the FunctionalJavascript test.
   *
   * @dataProvider providerLoadStrategy
   */
  public function testBundleAttachmentPerLoadStrategy(string $strategy, bool $attached): void {
    // With aggregation on, every asset ends up in one js_*.js file and no
    // assertion about file names would mean anything.
    $this->config('system.performance')->set('js.preprocess', FALSE)->save();
    $settings = [
      'region' => 'content',
      'label' => 'Front Chatbot',
      'chat_processor_plugin' => 'chatbot_test_processor',
    ];
    if ($strategy !== '') {
      $settings['load_strategy'] = $strategy;
    }
    $this->drupalPlaceBlock('ai_deepchat_block', $settings);

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $content = $this->getSession()->getPage()->getContent();
    $bundle_tag = '#<script[^>]+deepchat\\.bundle\\.js#';

    if ($attached) {
      $this->assertMatchesRegularExpression($bundle_tag, $content);
    }
    else {
      $this->assertDoesNotMatchRegularExpression($bundle_tag, $content);
    }

    // The loader ships inside deepchat-init.js, which is always attached, and
    // always knows what to import: the bundle URL travels in drupalSettings
    // even when there is no script tag.
    $this->assertMatchesRegularExpression('#<script[^>]+deepchat-init\\.js#', $content);
    $this->assertStringContainsString('deepchat.bundle.js', $content);
  }

  /**
   * Data provider for testBundleAttachmentPerLoadStrategy().
   *
   * @return array<string, array{string, bool}>
   *   Strategy to store on the block, and whether it attaches the bundle. The
   *   empty strategy stands for a block saved before the setting existed.
   */
  public static function providerLoadStrategy(): array {
    return [
      'default is eager' => ['', TRUE],
      'eager attaches the bundle' => ['eager', TRUE],
      'idle defers to the loader' => ['idle', FALSE],
      'interaction defers to the loader' => ['interaction', FALSE],
    ];
  }

}
