<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saving the Search API Processors form when AI Reranker is disabled.
 *
 * Regression test for the AI Reranker processor's "provider_model" and
 * "source_fields" elements being marked #required, which blocked saving the
 * Processors form for every index even when the processor was unchecked.
 * Search API builds the configuration form for every processor that
 * supports the index, enabled or not, but only validates it for enabled
 * processors. This is a FunctionalJavascript test, not a plain Functional
 * one, because the fix's #states binding is client-side behavior (the
 * "required" attribute toggling on the hidden fields as the AI Reranker
 * checkbox is checked/unchecked) that a BrowserKit-driven Functional test
 * never actually exercises in a browser.
 *
 * @group ai
 * @group 3586745
 */
#[RunTestsInSeparateProcesses]
class AiRerankerProcessorsFormTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'search_api',
    'search_api_test',
    'ai',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * The ID of the search index used for this test.
   *
   * @var string
   */
  protected string $indexId = 'ai_reranker_test_index';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'administer search_api',
      'access administration pages',
    ]);
    $this->drupalLogin($admin_user);

    Server::create([
      'id' => 'ai_reranker_test_server',
      'name' => 'AI Reranker test server',
      'backend' => 'search_api_test',
      'backend_config' => [],
    ])->save();

    Index::create([
      'id' => $this->indexId,
      'name' => 'AI Reranker test index',
      'server' => 'ai_reranker_test_server',
      'datasource_settings' => [
        'entity:node' => [],
      ],
    ])->save();
  }

  /**
   * Tests that the "required" state toggles with the AI Reranker checkbox.
   *
   * Before the fix, "provider_model" and "source_fields" were hard-coded
   * #required, so they stayed required regardless of the processor's own
   * enabled state. The fix ties required-ness to a #states binding on the
   * "status[ai_reranker]" checkbox instead, so this needs a real browser to
   * verify: the required attribute should appear and disappear as the
   * checkbox is toggled, without a page reload.
   */
  public function testRequiredStateTogglesWithCheckbox(): void {
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/processors');
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $this->takeScreenshot('1_form_loaded');

    $provider_model = $page->find('css', 'select[name="processors[ai_reranker][settings][provider_model]"]');
    $this->assertNotNull($provider_model, 'The AI provider and model select should be present.');
    $this->assertFalse($provider_model->hasAttribute('required'), 'The select must not be required while AI Reranker is disabled.');

    $page->checkField('status[ai_reranker]');
    $assert_session->waitForElement('css', 'select[name="processors[ai_reranker][settings][provider_model]"][required]');
    $this->takeScreenshot('2_checked_now_required');
    $this->assertTrue($provider_model->hasAttribute('required'), 'The select must become required once AI Reranker is enabled.');

    $page->uncheckField('status[ai_reranker]');
    $assert_session->waitForElementRemoved('css', 'select[name="processors[ai_reranker][settings][provider_model]"][required]');
    $this->takeScreenshot('3_unchecked_no_longer_required');
    $this->assertFalse($provider_model->hasAttribute('required'), 'The select must stop being required once AI Reranker is disabled again.');
  }

  /**
   * Tests that the Processors form saves with AI Reranker left disabled.
   *
   * Before the fix, the "AI provider and model" and "Source fields"
   * elements on the (hidden, disabled) AI Reranker settings tab were marked
   * #required, so HTML5/form-level required validation blocked the whole
   * form from submitting even though AI Reranker's own checkbox was never
   * checked.
   */
  public function testProcessorsFormSavesWithAiRerankerDisabled(): void {
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/processors');
    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('AI Reranker');
    $assert_session->checkboxNotChecked('status[ai_reranker]');
    $this->takeScreenshot('1_form_loaded');

    // Toggle an unrelated processor, matching the issue's own reproduction
    // steps, so the form actually persists a change and exercises the real
    // save path (not just the "no values changed" short-circuit). Before
    // the fix, this failed regardless of what else changed: the hidden,
    // disabled AI Reranker fields were #required and blocked the submit.
    $this->submitForm(['status[ignorecase]' => TRUE], 'Save');
    $this->takeScreenshot('2_saved');

    $assert_session->pageTextContains('The indexing workflow was successfully edited.');
    $assert_session->pageTextNotContains('AI provider and model field is required.');
    $assert_session->pageTextNotContains('Source fields field is required.');

    // AI Reranker must remain disabled; it was never opted into.
    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->indexId]);
    $index = $index_storage->load($this->indexId);
    $this->assertArrayNotHasKey('ai_reranker', $index->getProcessors());
  }

  /**
   * Tests that enabling AI Reranker still requires a provider and field.
   *
   * This is the counterpart to the disabled-processor regression above: the
   * fix must not silently remove validation once the processor is actually
   * enabled. ::validateConfigurationForm() (unchanged by the fix) is
   * responsible for this, since Search API only calls it for enabled
   * processors.
   *
   * Enabling AI Reranker now makes "provider_model" required client-side
   * too (the #states binding), so a real browser refuses to submit the
   * empty field before it ever reaches the server. The issue's own
   * reproduction steps cover that case separately ("with HTML5 validation
   * bypassed"), so this test disables native form validation to reach the
   * server-side backstop in ::validateConfigurationForm().
   */
  public function testEnablingAiRerankerStillRequiresConfiguration(): void {
    $this->drupalGet('admin/config/search/search-api/index/' . $this->indexId . '/processors');
    $this->takeScreenshot('1_form_loaded');

    $this->getSession()->executeScript(
      "document.querySelectorAll('form').forEach(function (form) { form.setAttribute('novalidate', 'novalidate'); });"
    );

    $this->submitForm([
      'status[ai_reranker]' => TRUE,
    ], 'Save');
    $this->takeScreenshot('2_validation_errors');

    $assert_session = $this->assertSession();
    $assert_session->pageTextContains('Select a valid AI provider and model that supports the rerank operation.');
    $assert_session->pageTextContains('Select at least one source field to send to the reranker.');
    $assert_session->pageTextNotContains('The indexing workflow was successfully edited.');

    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->indexId]);
    $index = $index_storage->load($this->indexId);
    $this->assertArrayNotHasKey('ai_reranker', $index->getProcessors());
  }

}
