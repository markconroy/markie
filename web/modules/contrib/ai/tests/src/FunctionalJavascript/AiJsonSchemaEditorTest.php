<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\FunctionalJavascript;

use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ai_json_schema CodeMirror editor behavior in the browser.
 *
 * @group ai
 * @group 3586536
 */
#[RunTestsInSeparateProcesses]
class AiJsonSchemaEditorTest extends BaseClassFunctionalJavascriptTests {

  /**
   * {@inheritdoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'system',
    'user',
  ];

  /**
   * The editor mounts, syncs to the hidden input and dispatches change events.
   *
   * The hidden input is updated programmatically by CodeMirror, which does not
   * fire input/change on its own. The editor must dispatch them so event-driven
   * hosts (e.g. the bpmn_io off-canvas modeller used by ECA and AI Agents) pick
   * up edited values. Without that dispatch the schema would silently save
   * empty there.
   */
  public function testEditorSyncsAndDispatchesChange(): void {
    $this->drupalLogin($this->drupalCreateUser(['administer ai']));
    $this->drupalGet('admin/config/ai/test-form-elements');

    // CodeMirror should mount and the plain-textarea fallback should hide.
    $this->assertNotEmpty($this->assertSession()->waitForElementVisible('css', '.cm-editor'));
    $this->assertSession()->elementExists('css', 'input[name="json_schema"]');
    $fallback = $this->getSession()->getPage()->find('css', '[data-ai-json-schema-fallback]');
    $this->assertNotEmpty($fallback);
    $this->assertFalse($fallback->isVisible());

    // Record change events fired on the hidden input.
    $this->getSession()->executeScript(<<<JS
      const input = document.querySelector('input[name="json_schema"]');
      input.dataset.changeCount = '0';
      input.addEventListener('change', () => {
        input.dataset.changeCount = String(parseInt(input.dataset.changeCount, 10) + 1);
      });
    JS);

    // Type into the CodeMirror editable region.
    $editable = $this->getSession()->getPage()->find('css', '.cm-content');
    $this->assertNotEmpty($editable);
    $editable->click();
    $this->getSession()->executeScript(<<<JS
      const cm = document.querySelector('.cm-content');
      cm.focus();
      document.getSelection().collapse(cm, 0);
      document.execCommand('insertText', false, ' ');
    JS);

    // The editor must have dispatched at least one change event for the edit.
    $changes = (int) $this->getSession()->evaluateScript(
      "parseInt(document.querySelector('input[name=\"json_schema\"]').dataset.changeCount, 10)"
    );
    $this->assertGreaterThan(0, $changes, 'Editing the schema dispatched a change event on the hidden input.');

    // The edited content must be synced into the hidden input value.
    $value = $this->getSession()->evaluateScript(
      "document.querySelector('input[name=\"json_schema\"]').value"
    );
    $this->assertNotSame('', $value);
    $this->assertNotSame('{"type": "object", "properties": {}}', $value, 'The hidden input reflects the edited content.');
  }

}
