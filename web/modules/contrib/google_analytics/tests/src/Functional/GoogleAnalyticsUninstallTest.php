<?php

namespace Drupal\Tests\google_analytics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test uninstall functionality of Google Analytics module.
 *
 * @group Google Analytics
 */
class GoogleAnalyticsUninstallTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['google_analytics'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $permissions = [
      'access administration pages',
      'administer google analytics',
      'administer modules',
    ];

    // User to set up google_analytics.
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Tests if the module cleans up the disk on uninstall.
   */
  public function testGoogleAnalyticsUninstall() {
    $cache_path = 'public://google_analytics';
    $ua_code = 'UA-123456-1';

    // Show tracker in pages.
    $this->config('google_analytics.settings')->set('account', $ua_code)->save();

    // Enable local caching of analytics.js.
    $this->config('google_analytics.settings')->set('cache', 1)->save();

    // Load page to get the analytics.js downloaded into local cache.
    $this->drupalGet('');

    // Test if the directory and analytics.js exists.
    $this->assertDirectoryExists($cache_path, 'Cache directory "public://google_analytics" has been found.');
    $this->assertFileExists($cache_path . '/analytics.js', 'Cached analytics.js tracking file has been found.');
    $this->assertFileExists($cache_path . '/analytics.js.gz', 'Cached analytics.js.gz tracking file has been found.');

    // Uninstall the module.
    $edit = [];
    $edit['uninstall[google_analytics]'] = TRUE;
    $this->drupalPostForm('admin/modules/uninstall', $edit, t('Uninstall'));
    $this->assertSession()->pageTextNotContains(\Drupal::translation()->translate('Configuration deletions'));
    $this->drupalPostForm(NULL, NULL, t('Uninstall'));
    $this->assertSession()->pageTextContains(t('The selected modules have been uninstalled.'));

    // Test if the directory and all files have been removed.
    $this->assertDirectoryNotExists($cache_path);
  }

}
