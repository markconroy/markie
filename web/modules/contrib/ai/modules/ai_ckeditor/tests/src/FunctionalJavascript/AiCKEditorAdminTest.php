<?php

namespace Drupal\Tests\ai_ckeditor\FunctionalJavascript;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\ai\FunctionalJavascriptTests\BaseClassFunctionalJavascriptTests;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;

/**
 * Tests the AI CKEditor admin toolbar configuration.
 *
 * @group ai_ckeditor
 * @group 3477173
 */
class AiCKEditorAdminTest extends BaseClassFunctionalJavascriptTests {

  use CKEditor5TestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'ai_ckeditor',
    'ckeditor5',
    'editor',
    'filter',
    'node',
    'user',
  ];

  /**
   * {@inheritDoc}
   */
  protected bool $videoRecording = TRUE;

  /**
   * {@inheritdoc}
   */
  protected string $screenshotModuleName = 'ai_ckeditor';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a text format.
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<br> <p> <h2> <h3> <h4> <h5> <h6> <strong> <em>',
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Clicks the Enabled checkbox for a given AI CKEditor plugin.
   *
   * The checkbox data-drupal-selector follows the pattern:
   * edit-editor-settings-plugins-ai-ckeditor-ai-plugins-{id}-enabled.
   *
   * @param string $plugin_id
   *   The plugin machine name (e.g. 'ai_ckeditor_spellfix').
   */
  protected function enablePlugin(string $plugin_id): void {
    $selector = sprintf(
      'input[data-drupal-selector="edit-editor-settings-plugins-ai-ckeditor-ai-plugins-%s-enabled"]',
      str_replace('_', '-', $plugin_id)
    );
    $this->getSession()->executeScript("document.querySelector('$selector').click();");
  }

  /**
   * Returns all discovered AI CKEditor plugin IDs and their labels.
   *
   * Uses the plugin manager so the assertion stays in sync with any new
   * plugins added to the module without needing to update this test.
   *
   * @return array<string, string>
   *   Keyed by plugin ID, values are the human-readable label strings.
   */
  protected function getAiCkeditorPlugins(): array {
    $definitions = $this->container
      ->get('plugin.manager.ai_ckeditor')
      ->getDefinitions();

    $plugins = [];
    foreach ($definitions as $id => $definition) {
      $plugins[$id] = (string) $definition['label'];
    }
    return $plugins;
  }

  /**
   * Asserts that the config section for a given AI CKEditor plugin is present.
   *
   * Each plugin renders a <details> element whose data-drupal-selector follows
   * the pattern: edit-editor-settings-plugins-ai-ckeditor-ai-plugins-{id}.
   * The summary inside contains the human-readable plugin label.
   *
   * @param string $plugin_id
   *   The plugin machine name (e.g. 'ai_ckeditor_spellfix').
   * @param string $label
   *   The expected human-readable label (e.g. 'Fix spelling').
   */
  protected function assertPluginConfigExists(string $plugin_id, string $label): void {
    $selector = sprintf(
      'details[data-drupal-selector="edit-editor-settings-plugins-ai-ckeditor-ai-plugins-%s"]',
      str_replace('_', '-', $plugin_id)
    );
    $this->assertSession()->elementExists('css', $selector);
    $this->assertSession()->elementTextContains('css', "$selector summary", $label);
  }

  /**
   * Triggers keydown and keyup events on a toolbar item element.
   *
   * Used to move buttons between the available and active toolbar areas in the
   * CKEditor 5 admin UI, which relies on keyboard events via Sortable.js.
   *
   * @param string $selector
   *   The CSS selector for the element.
   * @param string $key
   *   The key value (e.g. 'ArrowDown' to move to active, 'ArrowUp' to remove).
   *
   * @see \Drupal\Tests\ckeditor5\FunctionalJavascript\CKEditor5TestBase::triggerKeyUp()
   */
  protected function triggerKeyUp(string $selector, string $key): void {
    $script = <<<JS
(function (selector, key) {
  const btn = document.querySelector(selector);
  btn.dispatchEvent(new KeyboardEvent('keydown', { key }));
  btn.dispatchEvent(new KeyboardEvent('keyup', { key }));
})('{$selector}', '{$key}')
JS;
    $this->getSession()->executeScript($script);
  }

}
