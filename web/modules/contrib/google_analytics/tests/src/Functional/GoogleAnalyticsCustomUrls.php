<?php

namespace Drupal\Tests\google_analytics\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Test custom url functionality of Google Analytics module.
 *
 * @group Google Analytics
 */
class GoogleAnalyticsCustomUrls extends BrowserTestBase {

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
      'administer site configuration',
    ];

    // User to set up google_analytics.
    $this->admin_user = $this->drupalCreateUser($permissions);
  }

  /**
   * Tests if user password page urls are overridden.
   */
  public function testGoogleAnalyticsUserPasswordPage() {
    $base_path = base_path();
    $ua_code = 'UA-123456-1';
    $this->config('google_analytics.settings')->set('account', $ua_code)->save();

    $this->drupalGet('user/password', ['query' => ['name' => 'foo']]);
    $this->assertSession()->responseContains('ga("set", "page", "' . $base_path . 'user/password"');

    $this->drupalGet('user/password', ['query' => ['name' => 'foo@example.com']]);
    $this->assertSession()->responseContains('ga("set", "page", "' . $base_path . 'user/password"');

    $this->drupalGet('user/password');
    $this->assertSession()->responseNotContains('ga("set", "page",');
  }

}
