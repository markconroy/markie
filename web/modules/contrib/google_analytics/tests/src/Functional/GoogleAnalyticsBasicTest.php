<?php

namespace Drupal\Tests\google_analytics\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Test basic functionality of Google Analytics module.
 *
 * @group Google Analytics
 */
class GoogleAnalyticsBasicTest extends BrowserTestBase {

  /**
   * User without permissions to use snippets.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $noSnippetUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'block',
    'google_analytics',
    'help',
  ];

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
    $this->noSnippetUser = $this->drupalCreateUser($permissions);
    $permissions[] = 'add JS snippets for google analytics';
    $this->admin_user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->admin_user);

    // Place the block or the help is not shown.
    $this->drupalPlaceBlock('help_block', ['region' => 'help']);
  }

  /**
   * Tests if configuration is possible.
   */
  public function testGoogleAnalyticsConfiguration() {
    // Check if Configure link is available on 'Extend' page.
    // Requires 'administer modules' permission.
    $this->drupalGet('admin/modules');
    $this->assertSession()->responseContains('admin/config/system/google-analytics');

    // Check if Configure link is available on 'Status Reports' page.
    // NOTE: Link is only shown without UA code configured.
    // Requires 'administer site configuration' permission.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->responseContains('admin/config/system/google-analytics');

    // Check for setting page's presence.
    $this->drupalGet('admin/config/system/google-analytics');
    $this->assertSession()->responseContains(t('Web Property ID'));

    // Check for account code validation.
    $edit['google_analytics_account'] = $this->randomMachineName(2);
    $this->drupalPostForm('admin/config/system/google-analytics', $edit, t('Save configuration'));
    $this->assertSession()->responseContains(t('A valid Google Analytics Web Property ID is case sensitive and formatted like UA-xxxxxxx-yy.'));

    // User should have access to code snippets.
    $this->assertFieldByName('google_analytics_codesnippet_create');
    $this->assertFieldByName('google_analytics_codesnippet_before');
    $this->assertFieldByName('google_analytics_codesnippet_after');
    $this->assertNoFieldByXPath("//textarea[@name='google_analytics_codesnippet_create' and @disabled='disabled']", NULL, '"Create only fields" is enabled.');
    $this->assertNoFieldByXPath("//textarea[@name='google_analytics_codesnippet_before' and @disabled='disabled']", NULL, '"Code snippet (before)" is enabled.');
    $this->assertNoFieldByXPath("//textarea[@name='google_analytics_codesnippet_after' and @disabled='disabled']", NULL, '"Code snippet (after)" is enabled.');

    // Login as user without JS permissions.
    $this->drupalLogin($this->noSnippetUser);
    $this->drupalGet('admin/config/system/google-analytics');

    // User should *not* have access to snippets, but create fields.
    $this->assertFieldByName('google_analytics_codesnippet_create');
    $this->assertFieldByName('google_analytics_codesnippet_before');
    $this->assertFieldByName('google_analytics_codesnippet_after');
    $this->assertNoFieldByXPath("//textarea[@name='google_analytics_codesnippet_create' and @disabled='disabled']", NULL, '"Create only fields" is enabled.');
    $this->assertFieldByXPath("//textarea[@name='google_analytics_codesnippet_before' and @disabled='disabled']", NULL, '"Code snippet (before)" is disabled.');
    $this->assertFieldByXPath("//textarea[@name='google_analytics_codesnippet_after' and @disabled='disabled']", NULL, '"Code snippet (after)" is disabled.');
  }

  /**
   * Tests if help sections are shown.
   */
  public function testGoogleAnalyticsHelp() {
    // Requires help and block module and help block placement.
    $this->drupalGet('admin/config/system/google-analytics');
    $this->assertText('Google Analytics is a free (registration required) website traffic and marketing effectiveness service.');

    // Requires help.module.
    $this->drupalGet('admin/help/google_analytics');
    $this->assertText('Google Analytics adds a web statistics tracking system to your website.');
  }

  /**
   * Tests if page visibility works.
   */
  public function testGoogleAnalyticsPageVisibility() {
    // Verify that no tracking code is embedded into the webpage; if there is
    // only the module installed, but UA code not configured. See #2246991.
    $this->drupalGet('');
    $this->assertSession()->responseNotContains('https://www.google-analytics.com/analytics.js');

    $ua_code = 'UA-123456-1';
    $this->config('google_analytics.settings')->set('account', $ua_code)->save();

    // Show tracking on "every page except the listed pages".
    $this->config('google_analytics.settings')->set('visibility.request_path_mode', 0)->save();
    // Disable tracking on "admin*" pages only.
    $this->config('google_analytics.settings')->set('visibility.request_path_pages', "/admin\n/admin/*")->save();
    // Enable tracking only for authenticated users only.
    $this->config('google_analytics.settings')->set('visibility.user_role_roles', [AccountInterface::AUTHENTICATED_ROLE => AccountInterface::AUTHENTICATED_ROLE])->save();

    // Check tracking code visibility.
    $this->drupalGet('');
    $this->assertSession()->responseContains($ua_code);

    // Test whether tracking code is not included on pages to omit.
    $this->drupalGet('admin');
    $this->assertSession()->responseNotContains($ua_code);
    $this->drupalGet('admin/config/system/google-analytics');
    // Checking for tracking URI here, as $ua_code is displayed in the form.
    $this->assertSession()->responseNotContains('https://www.google-analytics.com/analytics.js');

    // Test whether tracking code display is properly flipped.
    $this->config('google_analytics.settings')->set('visibility.request_path_mode', 1)->save();
    $this->drupalGet('admin');
    $this->assertSession()->responseContains($ua_code);
    $this->drupalGet('admin/config/system/google-analytics');
    // Checking for tracking URI here, as $ua_code is displayed in the form.
    $this->assertSession()->responseContains('https://www.google-analytics.com/analytics.js');
    $this->drupalGet('');
    $this->assertSession()->responseNotContains($ua_code);

    // Test whether tracking code is not display for anonymous.
    $this->drupalLogout();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains($ua_code);

    // Switch back to every page except the listed pages.
    $this->config('google_analytics.settings')->set('visibility.request_path_mode', 0)->save();
    // Enable tracking code for all user roles.
    $this->config('google_analytics.settings')->set('visibility.user_role_roles', [])->save();

    $base_path = base_path();

    // Test whether 403 forbidden tracking code is shown if user has no access.
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(403);
    $this->assertSession()->responseContains($base_path . '403.html');

    // Test whether 404 not found tracking code is shown on non-existent pages.
    $this->drupalGet($this->randomMachineName(64));
    $this->assertSession()->statusCodeEquals(404);
    $this->assertSession()->responseContains($base_path . '404.html');
  }

  /**
   * Tests if tracking code is properly added to the page.
   */
  public function testGoogleAnalyticsTrackingCode() {
    $ua_code = 'UA-123456-2';
    $this->config('google_analytics.settings')->set('account', $ua_code)->save();

    // Show tracking code on every page except the listed pages.
    $this->config('google_analytics.settings')->set('visibility.request_path_mode', 0)->save();
    // Enable tracking code for all user roles.
    $this->config('google_analytics.settings')->set('visibility.user_role_roles', [])->save();

    /* Sample JS code as added to page:
    <script type="text/javascript" src="/sites/all/modules/google_analytics/google_analytics.js?w"></script>
    <script>
    (function(i,s,o,g,r,a,m){
    i["GoogleAnalyticsObject"]=r;i[r]=i[r]||function(){
    (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
    m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,"script","https://www.google-analytics.com/analytics.js","ga");
    ga('create', 'UA-123456-7');
    ga('send', 'pageview');
    </script>
    <!-- End Google Analytics -->
     */

    // Test whether tracking code uses latest JS.
    $this->config('google_analytics.settings')->set('cache', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('https://www.google-analytics.com/analytics.js');

    // Test whether anonymize visitors IP address feature has been enabled.
    $this->config('google_analytics.settings')->set('privacy.anonymizeip', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains('ga("set", "anonymizeIp", true);');
    // Enable anonymizing of IP addresses.
    $this->config('google_analytics.settings')->set('privacy.anonymizeip', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("set", "anonymizeIp", true);');

    // Test if track Enhanced Link Attribution is enabled.
    $this->config('google_analytics.settings')->set('track.linkid', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("require", "linkid", "linkid.js");');

    // Test if track Enhanced Link Attribution is disabled.
    $this->config('google_analytics.settings')->set('track.linkid', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains('ga("require", "linkid", "linkid.js");');

    // Test if tracking of url fragments is enabled.
    $this->config('google_analytics.settings')->set('track.urlfragments', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("set", "page", location.pathname + location.search + location.hash);');

    // Test if tracking of url fragments is disabled.
    $this->config('google_analytics.settings')->set('track.urlfragments', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains('ga("set", "page", location.pathname + location.search + location.hash);');

    // Test if tracking of User ID is enabled.
    $this->config('google_analytics.settings')->set('track.userid', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains(', {"cookieDomain":"auto","userId":"');

    // Test if tracking of User ID is disabled.
    $this->config('google_analytics.settings')->set('track.userid', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains(', {"cookieDomain":"auto","userId":"');

    // Test if track display features is enabled.
    $this->config('google_analytics.settings')->set('track.displayfeatures', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("require", "displayfeatures");');

    // Test if track display features is disabled.
    $this->config('google_analytics.settings')->set('track.displayfeatures', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseNotContains('ga("require", "displayfeatures");');

    // Test whether single domain tracking is active.
    $this->drupalGet('');
    $this->assertSession()->responseContains('{"cookieDomain":"auto"}');

    // Enable "One domain with multiple subdomains".
    $this->config('google_analytics.settings')->set('domain_mode', 1)->save();
    $this->drupalGet('');

    // Test may run on localhost, an ipaddress or real domain name.
    // TODO: Workaround to run tests successfully. This feature cannot tested
    // reliable.
    global $cookie_domain;
    if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
      $this->assertSession()->responseContains('{"cookieDomain":"' . $cookie_domain . '"}');
    }
    else {
      // Special cases, Localhost and IP addresses don't show 'cookieDomain'.
      $this->assertSession()->responseNotContains('{"cookieDomain":"' . $cookie_domain . '"}');
    }

    // Enable "Multiple top-level domains" tracking.
    $this->config('google_analytics.settings')
      ->set('domain_mode', 2)
      ->set('cross_domains', "www.example.com\nwww.example.net")
      ->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("create", "' . $ua_code . '", {"cookieDomain":"auto","allowLinker":true');
    $this->assertSession()->responseContains('ga("require", "linker");');
    $this->assertSession()->responseContains('ga("linker:autoLink", ["www.example.com","www.example.net"]);');
    $this->assertSession()->responseContains('"trackDomainMode":2,');
    $this->assertSession()->responseContains('"trackCrossDomains":["www.example.com","www.example.net"]');
    $this->config('google_analytics.settings')->set('domain_mode', 0)->save();

    // Test whether debugging script has been enabled.
    $this->config('google_analytics.settings')->set('debug', 1)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('https://www.google-analytics.com/analytics_debug.js');

    // Check if text and link is shown on 'Status Reports' page.
    // Requires 'administer site configuration' permission.
    $this->drupalGet('admin/reports/status');
    $this->assertSession()->responseContains(t('Google Analytics module has debugging enabled. Please disable debugging setting in production sites from the <a href=":url">Google Analytics settings page</a>.', [':url' => Url::fromRoute('google_analytics.admin_settings_form')->toString()]));

    // Test whether debugging script has been disabled.
    $this->config('google_analytics.settings')->set('debug', 0)->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('https://www.google-analytics.com/analytics.js');

    // Test whether the CREATE and BEFORE and AFTER code is added to the
    // tracking code.
    $codesnippet_create = [
      'cookieDomain' => 'foo.example.com',
      'cookieName' => 'myNewName',
      'cookieExpires' => 20000,
      'allowAnchor' => TRUE,
      'sampleRate' => 4.3,
    ];
    $this->config('google_analytics.settings')
      ->set('codesnippet.create', $codesnippet_create)
      ->set('codesnippet.before', 'ga("set", "forceSSL", true);')
      ->set('codesnippet.after', 'ga("create", "UA-123456-3", {"name": "newTracker"});if(1 == 1 && 2 < 3 && 2 > 1){console.log("Google Analytics: Custom condition works.");}ga("newTracker.send", "pageview");')
      ->save();
    $this->drupalGet('');
    $this->assertSession()->responseContains('ga("create", "' . $ua_code . '", {"cookieDomain":"foo.example.com","cookieName":"myNewName","cookieExpires":20000,"allowAnchor":true,"sampleRate":4.3});');
    $this->assertSession()->responseContains('ga("set", "forceSSL", true);');
    $this->assertSession()->responseContains('ga("create", "UA-123456-3", {"name": "newTracker"});');
    $this->assertSession()->responseContains('if(1 == 1 && 2 < 3 && 2 > 1){console.log("Google Analytics: Custom condition works.");}');
  }

}
