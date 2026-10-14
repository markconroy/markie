<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\FunctionalJavascriptTests;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the structured results modal of the DeepChat block.
 *
 * Places the ai_deepchat_block with the chatbot_test_processor fixture, which
 * appends a fixed structured results payload to every reply, and checks the
 * full modal round trip: the on-demand core/drupal.dialog attachment, opening
 * the modal from the structured results button and closing it again through
 * its Close button.
 *
 * The deep-chat web component renders its messages into an (open) shadow
 * root that normal Mink/CSS selectors cannot reach, so the conversation is
 * driven through the component's public JS API (submitUserMessage() /
 * getMessages()) and the structured results button is clicked with script.
 * The modal itself is a plain Drupal.dialog() attached to the page body, so
 * it is asserted and closed through regular Mink interaction.
 *
 * @group ai_chatbot
 * @group 3586670
 */
#[RunTestsInSeparateProcesses]
class DeepChatStructuredResultsModalTest extends BaseClassFunctionalJavascriptTests {

  use DeepChatTestTrait;

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
  protected string $screenshotModuleName = 'ai_chatbot';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * Tests opening and closing the structured results modal.
   */
  public function testStructuredResultsModalOpensAndCloses(): void {
    $this->drupalPlaceBlock('ai_deepchat_block', [
      'region' => 'content',
      'label' => 'Structured Results Chatbot',
      'chat_processor_plugin' => 'chatbot_test_processor',
      'plugin_configuration' => [
        'greeting' => 'Hello',
        'show_structured_results' => TRUE,
      ],
    ]);

    $user = $this->drupalCreateUser([
      'access content',
      'access deepchat api',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('<front>');
    $assert = $this->assertSession();

    // The block config enables structured results, so build() must attach
    // the core/drupal.dialog stack on-demand; without it the modal below
    // could not open (Drupal.dialog would be undefined).
    $this->assertTrue(
      (bool) $this->getSession()->evaluateScript("typeof Drupal.dialog === 'function'"),
      'Drupal.dialog is available because structured results are enabled.'
    );

    $this->waitForDeepChatReady();
    $this->submitDeepChatMessage('Show me the data');
    $this->waitForStructuredResultsButton();
    $this->takeScreenshot('structured_results_1_replied');

    // Open the modal through the structured results button in the reply.
    $this->getSession()->executeScript(
      "document.querySelector('deep-chat').shadowRoot.querySelector('.structured-results').click();"
    );
    $dialog = $assert->waitForElementVisible('css', '.ui-dialog');
    $this->assertNotNull($dialog, 'The structured results modal opened.');
    $assert->elementTextContains('css', '.ui-dialog .ui-dialog-title', 'Structured results');
    // The fixture's fixed payload is rendered inside the modal.
    $assert->elementTextContains('css', '.ui-dialog .structured-results-dump', 'fixture');
    $this->takeScreenshot('structured_results_2_modal_open');

    // The Close button must close the modal again without a JS error. A
    // plain Drupal.dialog() close only hides the jQuery UI wrapper (only
    // dialog.ajax.js removes it from the DOM), so wait for it to become
    // invisible rather than to disappear.
    $close = $dialog->find('css', '.ui-dialog-buttonpane button');
    $this->assertNotNull($close, 'The modal has a Close button.');
    $close->click();
    $this->assertTrue(
      $this->getSession()->wait(10000, "document.querySelector('.ui-dialog') === null || document.querySelector('.ui-dialog').offsetParent === null"),
      'The structured results modal closed again.'
    );
    $this->takeScreenshot('structured_results_3_modal_closed');
  }

  /**
   * Waits until the reply with the structured results button has rendered.
   */
  protected function waitForStructuredResultsButton(): void {
    $arrived = $this->getSession()->wait(
      15000,
      "document.querySelector('deep-chat').shadowRoot.querySelector('.structured-results') !== null"
    );
    $this->assertTrue($arrived, 'The reply rendered the structured results button within the timeout.');
  }

}
