<?php

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI Disclosure checkbox of the settings form.
 *
 * @group ai_translate
 * @requires module ai_disclosure
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class AiTranslateSettingsFormDisclosureTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_translate',
    'ai',
    'ai_disclosure',
    'node',
    'language',
    'content_translation',
    'help',
  ];

  /**
   * Tests that the checkbox shows, matches the runtime default and saves.
   */
  public function testDisclosureCheckbox(): void {
    $session = $this->assertSession();
    $this->drupalLogin($this->drupalCreateUser(['manage ai translation prompts']));
    $url = Url::fromRoute('ai_translate.settings_form');

    // A new install has the setting on.
    $this->drupalGet($url);
    $session->checkboxChecked('ai_disclosure_apply');

    // A site updated from before the setting existed has no key: unchecked,
    // as the code treats a missing key as off.
    $this->config('ai_translate.settings')->clear('ai_disclosure_apply')->save();
    $this->drupalGet($url);
    $session->checkboxNotChecked('ai_disclosure_apply');

    $this->submitForm(['ai_disclosure_apply' => TRUE], 'Save configuration');
    $this->assertTrue($this->config('ai_translate.settings')->get('ai_disclosure_apply'));
  }

}
