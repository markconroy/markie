<?php

namespace Drupal\Tests\metatag_views\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\metatag\Functional\MetatagHelperTrait;

/**
 * Confirm a metatag value of "0" is preserved when saving a view.
 *
 * Regression test for https://www.drupal.org/project/metatag/issues/3471340:
 * the Expires meta tag accepts "0" (to stop the page being cached), but the
 * submitted value was filtered with empty(), which treats "0" as empty, so it
 * was silently dropped and never saved.
 *
 * @group metatag
 */
class MetatagViewsExpiresZeroTest extends BrowserTestBase {

  // Contains helper methods.
  use MetatagHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Modules for core functionality.
    'block',
    'field',
    'field_ui',
    'help',
    'node',
    'user',

    // Views. Duh. Enable the Views UI so it can be fully tested.
    'views',
    'views_ui',

    // Contrib dependencies.
    'token',
    'metatag',

    // This module.
    'metatag_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Place the local actions block in the theme so that we can assert the
    // presence of local actions and such.
    $this->drupalPlaceBlock('local_actions_block', [
      'region' => 'content',
      'theme' => $this->defaultTheme,
    ]);
  }

  /**
   * Confirm an Expires value of "0" is saved and not treated as empty.
   */
  public function testExpiresZeroIsSaved() {
    // Log in as user 1.
    $this->loginUser1();

    // Enable the Archive view via the Views admin list.
    $this->drupalGet('/admin/structure/views');
    $this->assertSession()->statusCodeEquals(200);
    $this->clickLink('Enable', 0);

    // Load the Archive view.
    $this->drupalGet('/admin/structure/views/view/archive');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Meta tags:');
    $this->assertSession()->pageTextContains('Using defaults');

    // Open the 'page' display and the metatags settings dialog.
    $this->clickLink('Page');
    $this->clickLink('Using defaults');

    // Enter "0" in the Expires field and apply.
    $this->assertSession()->fieldExists('expires');
    $this->submitForm(['expires' => '0'], 'Apply');

    // The display should now report overridden metatags.
    $this->assertSession()->pageTextContains('Overridden');

    // Save the view.
    $this->submitForm([], 'Save');

    // Reopen the metatags settings dialog for the 'page' display.
    $this->drupalGet('/admin/structure/views/view/archive');
    $this->clickLink('Page');
    $this->clickLink('Overridden');

    // The Expires value of "0" must have been preserved, not dropped.
    $this->assertSession()->fieldValueEquals('expires', '0');
  }

}
