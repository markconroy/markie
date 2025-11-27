<?php

namespace Drupal\Tests\klaro\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Check if local klaro logic can replace html tag src attributes.
 */
class TemplateProcessFinalHtmlTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'klaro', 'klaro__testing',
  ];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to bypass access content.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A user with permission to bypass access content, use klaro.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $klaroUser;

  /**
   * A user with permission to bypass administer klaro.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->user = $this->drupalCreateUser(['access content']);
    $this->adminUser = $this->drupalCreateUser(['administer klaro']);
    $this->klaroUser = $this->drupalCreateUser(['access content', 'use klaro']);

    $assert_session = $this->assertSession();
    $this->drupalLogin($this->adminUser);

    // Check if user interface can be reached.
    $this->drupalGet('admin/config/user-interface/klaro');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Klaro! settings');

    $this->submitForm(
          [
            'final_html' => TRUE,
          ], 'Save configuration'
      );
    $assert_session->pageTextNotContains('Error Message', 'Could not update config.');
    $assert_session->checkboxChecked('final_html');

    $this->drupalGet('admin/config/user-interface/klaro/services');
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('Example');

    $this->drupalLogout();
  }

  /**
   * Check if iframes / scripts will be replaced by "processFinalHtml".
   */
  public function testTemplate() {
    $assert_session = $this->assertSession();

    // Enter user necessary for rendering of test stages.
    $this->drupalLogin($this->user);

    // Run render iframe stage.
    $this->drupalGet('klaro--testing/iframes');
    $assert_session->statusCodeEquals(200);
    // Make sure klaro has not been loaded.
    $assert_session->elementNotExists('xpath', '//script[contains(@src, "/dist/klaro-no-translations-no-css.js")]');
    // Check if the element has not been loaded.
    $assert_session->elementExists('xpath', '//iframe[contains(@src, "//video.example.org/iframe")]');
    $assert_session->elementExists('xpath', '//iframe[contains(@src, "//video.example.com/iframe")]');

    $this->drupalGet('klaro--testing/scripts');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('xpath', '//script[contains(@src, "//example.example.org/js/script.js")]');
    $assert_session->elementExists('xpath', '//script[contains(@src, "//example.example.com/js/script.js")]');

    $this->drupalLogin($this->klaroUser);

    // Run render iframe stage.
    $this->drupalGet('klaro--testing/iframes');
    $assert_session->statusCodeEquals(200);
    // lib.
    $assert_session->elementExists('xpath', '//script[contains(@src, "/dist/klaro-no-translations-no-css.js")]');
    // App iframe.
    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.org/iframe")]');
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.org/iframe")][@data-name="app_example"]');
    // Unknown iframe.
    $assert_session->elementExists('xpath', '//iframe[contains(@src, "//video.example.com/iframe")]');

    $this->drupalGet('klaro--testing/scripts');
    $assert_session->statusCodeEquals(200);
    // App script.
    $assert_session->elementNotExists('xpath', '//script[contains(@src, "//example.example.org/js/script.js")]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.org/js/script.js")][@data-name="app_example"]');
    // Unknown script.
    $assert_session->elementExists('xpath', '//script[contains(@src, "//example.example.com/js/script.js")]');

    $this->drupalGet('klaro--testing/combined');
    $assert_session->statusCodeEquals(200);
    // lib.
    $assert_session->elementExists('xpath', '//script[contains(@src, "/dist/klaro-no-translations-no-css.js")]');

    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.org/iframe")]');
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.org/iframe")][@data-name="app_example"]');
    $assert_session->elementExists('xpath', '//iframe[contains(@src, "//video.example.com/iframe")]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.org/js/script.js")][@data-name="app_example"]');
    // Unknown script.
    $assert_session->elementExists('xpath', '//script[contains(@src, "//example.example.com/js/script.js")]');

    // Test unknown.
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/config/user-interface/klaro');
    $assert_session->statusCodeEquals(200);

    $this->submitForm(
          [
            'block_unknown' => TRUE,
            'log_unknown_resources' => TRUE,
          ], 'Save configuration'
      );
    $assert_session->pageTextNotContains('Error Message', 'Could not update config.');
    $assert_session->checkboxChecked('block_unknown');
    $assert_session->checkboxChecked('log_unknown_resources');

    $this->drupalLogin($this->klaroUser);

    $this->drupalGet('klaro--testing/iframes');
    $assert_session->statusCodeEquals(200);
    // lib.
    $assert_session->elementExists('xpath', '//script[contains(@src, "/dist/klaro-no-translations-no-css.js")]');
    // App iframe.
    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.org/iframe")]');
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.org/iframe")][@data-name="app_example"]');
    // Unknown iframe.
    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.com/iframe")]');
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.com/iframe")][@data-name="unknown_app"]');

    $this->drupalGet('klaro--testing/scripts');
    $assert_session->statusCodeEquals(200);
    // App script.
    $assert_session->elementNotExists('xpath', '//script[contains(@src, "//example.example.org/js/script.js")]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.org/js/script.js")][@data-name="app_example"]');
    // Unknown script.
    $assert_session->elementNotExists('xpath', '//script[contains(@src, "//example.example.com/js/script.js")]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.com/js/script.js")][@data-name="unknown_app"]');

    $this->drupalGet('klaro--testing/combined');
    $assert_session->statusCodeEquals(200);

    $assert_session->elementExists('xpath', '//script[contains(@src, "/dist/klaro-no-translations-no-css.js")]');
    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.org/iframe")]');
    $assert_session->elementNotExists('xpath', '//iframe[contains(@src, "//video.example.com/iframe")]');

    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.org/iframe")][@data-name="app_example"]');
    $assert_session->elementExists('xpath', '//iframe[contains(@data-src, "//video.example.com/iframe")][@data-name="unknown_app"]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.org/js/script.js")][@data-name="app_example"]');
    $assert_session->elementExists('xpath', '//script[contains(@data-src, "//example.example.com/js/script.js")][@data-name="unknown_app"]');
  }

}
