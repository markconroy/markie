<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\FunctionalJavascript\Plugin\FieldWidgetAction;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;

/**
 * Tests the result message for an automator that keeps existing values.
 *
 * The summary action runs with clearEntity = FALSE, so the author's own
 * summary is still in the field while the automator runs. Uses mocked LLM
 * responses keyed on the field's main text.
 *
 * @group ai_automators
 * @group 3585987
 */
class AutomatorSummaryResultMessageTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'node',
    'file',
    'ai_automators',
    'field',
    'user',
    'text',
    'field_ui',
    'field_widget_actions',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_automators';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Result messaging ships in Field Widget Actions 1.5.0.
    // @todo Remove once ai requires drupal/field_widget_actions ^1.5.
    if (!method_exists(FieldWidgetActionBase::class, 'reportResultAjax')) {
      $this->markTestSkipped('Requires Field Widget Actions with result messaging (>=1.5.0).');
    }

    $this->drupalCreateContentType(['type' => 'article', 'name' => 'Article']);
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'type' => 'text_with_summary',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Text',
      // Without the summary input the widget renders the summary as a hidden
      // value element, which drops whatever the automator writes into it.
      'settings' => ['display_summary' => TRUE],
    ])->save();

    $prompt = 'Summarize the following text: {{ context }}';
    \Drupal::entityTypeManager()->getStorage('ai_automator')->create([
      'id' => 'node.article.field_text.default',
      'label' => 'Text Default',
      'rule' => 'llm_text_create_summary',
      'input_mode' => 'base',
      'weight' => 100,
      'worker_type' => 'field_widget_actions',
      'entity_type' => 'node',
      'bundle' => 'article',
      'field_name' => 'field_text',
      'edit_mode' => FALSE,
      'base_field' => 'field_text',
      'prompt' => $prompt,
      'token' => '',
      'plugin_config' => [
        'automator_enabled' => 1,
        'automator_rule' => 'llm_text_create_summary',
        'automator_mode' => 'base',
        'automator_base_field' => 'field_text',
        'automator_prompt' => $prompt,
        'automator_token' => '',
        'automator_edit_mode' => 0,
        'automator_label' => 'Text Default',
        'automator_weight' => '100',
        'automator_worker_type' => 'field_widget_actions',
        'automator_ai_provider' => 'echoai',
        'automator_ai_model' => 'default',
      ],
    ])->save();

    EntityFormDisplay::load('node.article.default')
      ->setComponent('field_text', [
        'type' => 'text_textarea_with_summary',
        'region' => 'content',
        'settings' => [
          'rows' => 9,
          'summary_rows' => 3,
          'placeholder' => '',
          'show_summary' => TRUE,
        ],
        'third_party_settings' => [
          'field_widget_actions' => [
            'summary-action' => [
              'plugin_id' => 'summary_textarea_with_summary',
              'enabled' => TRUE,
              'button_label' => 'Suggest Summary',
              'weight' => 0,
              'show_result_message' => TRUE,
              'settings' => ['automator_id' => 'node.article.field_text.default'],
            ],
          ],
        ],
      ])
      ->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'create article content',
    ]));
  }

  /**
   * Clicks the summary action with the author's own summary already typed in.
   *
   * @param string $text
   *   The field's main text, which selects the mocked response.
   */
  protected function runSummaryAction(string $text): void {
    $this->drupalGet('/node/add/article');
    $page = $this->getSession()->getPage();
    $page->fillField('title[0][value]', 'Test article');
    $page->fillField('field_text[0][value]', $text);
    $page->fillField('field_text[0][summary]', 'My own summary.');
    $this->click('.field-widget-action-summary_textarea_with_summary');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * A generated summary replacing the author's own reports success.
   */
  public function testSummaryReplacesExistingValue(): void {
    $this->runSummaryAction('Monkeys are cool animals.');

    $assert = $this->assertSession();
    $assert->fieldValueEquals('field_text[0][summary]', 'Generated summary.');
    $assert->pageTextContains('A suggestion was added to Text.');
    $assert->pageTextNotContains('No suggestion was returned for Text.');
  }

  /**
   * An empty answer keeps the author's summary and reports nothing new.
   *
   * The field holds the author's summary before and after, so an emptiness
   * check would call this a success.
   */
  public function testSummaryEmptyResponseKeepsAuthorText(): void {
    $this->runSummaryAction('Nothing to summarize here.');

    $assert = $this->assertSession();
    $assert->fieldValueEquals('field_text[0][summary]', 'My own summary.');
    $assert->pageTextContains('No suggestion was returned for Text.');
    $assert->pageTextNotContains('A suggestion was added to Text.');
  }

}
