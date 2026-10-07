<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_api_explorer\FunctionalJavascript\Plugin\AiApiExplorer;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests the Chat Explorer.
 *
 * @group ai_api_explorer
 * @group 3577469
 */
class ChatExplorerTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'file',
    'ai_api_explorer',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_api_explorer';

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->setDefaultProvider('chat', 'echoai', 'gpt-test');
  }

  /**
   * Tests to create a chat message and check the response.
   */
  public function testCreateChatMessageAndResponse(): void {
    $admin = $this->drupalCreateUser([
      'administer site configuration',
      'access content',
      'access ai prompt',
    ]);
    $this->drupalLogin($admin);
    $this->drupalGet('/admin/config/ai/explorers/chat_generator');
    // Take a screenshot before interaction.
    $this->takeScreenshot('1_before_message');

    // Get the page.
    $page = $this->getSession()->getPage();

    // Fill in the chat message.
    $page->fillField('message_1', 'Hello There');

    // Take a screenshot after filling the form.
    $this->takeScreenshot('2_filled_form');

    // Press the Ask The AI button.
    $this->click('#edit-submit');

    // Take a screenshot after clicking the  button.
    $this->takeScreenshot('3_after_click_button');

    // Wait for ajax to complete.
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Take a screenshot after the ajax call.
    $this->takeScreenshot('4_after_ajax_call');

    // Wait for the response text to appear in the DOM before asserting. The
    // return value has to be checked, since waitForText returns NULL rather
    // than failing when the text never turns up.
    $this->assertTrue($this->assertSession()->waitForText('Hello! How can I help you today?'));

    // Find the response.
    $this->assertSession()->pageTextContains('Hello! How can I help you today? 😊');

    // The response metadata is rendered in a collapsed details, so nothing
    // inside it is visible to the driver until it is opened.
    $summary = $this->assertSession()->waitForElementVisible('css', 'details.ai-response-metadata summary');
    $this->assertNotEmpty($summary, 'The response metadata details is shown.');
    $summary->click();

    // Take a screenshot of the metadata.
    $this->takeScreenshot('5_response_metadata');

    // The token usage the provider reported for this request.
    $this->assertMetadataRow('Input tokens', '9');
    $this->assertMetadataRow('Output tokens', '10');
    $this->assertMetadataRow('Total tokens', '19');
    $this->assertMetadataRow('Reasoning tokens', '0');
    $this->assertMetadataRow('Cached tokens', '0');

    // The rate limits are a separate section, reporting what is left of the
    // quota rather than what this request used.
    $this->assertMetadataRow('Request limit', '500');
    $this->assertMetadataRow('Token limit', '30000');
    $this->assertMetadataRow('Requests remaining', '499');
    $this->assertMetadataRow('Tokens remaining', '29981');
    $this->assertMetadataRow('Requests reset in (seconds)', '120');
    $this->assertMetadataRow('Tokens reset in (seconds)', '1');
  }

  /**
   * Asserts that the response metadata shows a value against a label.
   *
   * @param string $label
   *   The label in the first column.
   * @param string $value
   *   The value expected in the second column.
   */
  protected function assertMetadataRow(string $label, string $value): void {
    $row = $this->getSession()->getPage()->find(
      'xpath',
      '//details[contains(@class, "ai-response-metadata")]//tr[td[normalize-space() = "' . $label . '"]]'
    );
    $this->assertNotNull($row, sprintf('The metadata shows a row labelled "%s".', $label));

    $cells = $row->findAll('css', 'td');
    $this->assertCount(2, $cells, sprintf('The "%s" row has a label and a value.', $label));
    $this->assertSame($value, trim($cells[1]->getText()), sprintf('The "%s" row shows the reported value.', $label));
  }

}
