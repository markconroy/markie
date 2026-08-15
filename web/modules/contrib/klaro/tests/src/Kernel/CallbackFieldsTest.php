<?php

namespace Drupal\Tests\klaro\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\klaro\Entity\KlaroApp;
use Drupal\user\Entity\Role;

/**
 * Tests on_init, on_accept, on_decline callback fields and the update hook.
 *
 * @group klaro
 */
class CallbackFieldsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['klaro', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('user_role');
    $this->installConfig(['user']);
    $this->installConfig(['klaro']);
    $this->installEntitySchema('klaro_app');

    $role = Role::load('anonymous');
    $role->grantPermission('use klaro');
    $role->save();
  }

  /**
   * Tests that new callback fields default to empty string.
   */
  public function testCallbackFieldDefaults() {
    $app = KlaroApp::create([
      'id' => 'test_app',
      'label' => 'Test App',
      'status' => TRUE,
    ]);
    $app->save();

    $loaded = KlaroApp::load('test_app');
    $this->assertSame('', $loaded->onInit(), 'onInit() defaults to empty string.');
    $this->assertSame('', $loaded->onAccept(), 'onAccept() defaults to empty string.');
    $this->assertSame('', $loaded->onDecline(), 'onDecline() defaults to empty string.');
  }

  /**
   * Tests that callback fields can be set and retrieved.
   */
  public function testCallbackFieldSetAndGet() {
    $app = KlaroApp::create([
      'id' => 'test_app_callbacks',
      'label' => 'Test App Callbacks',
      'status' => TRUE,
    ]);
    $app->setOnInit('console.log("init");');
    $app->setOnAccept('console.log("accept");');
    $app->setOnDecline('console.log("decline");');
    $app->save();

    $loaded = KlaroApp::load('test_app_callbacks');
    $this->assertSame('console.log("init");', $loaded->onInit());
    $this->assertSame('console.log("accept");', $loaded->onAccept());
    $this->assertSame('console.log("decline");', $loaded->onDecline());
  }

  /**
   * Tests that KlaroHelper includes on_init/on_accept/on_decline in settings.
   */
  public function testCallbackFieldsInSettings() {
    KlaroApp::create([
      'id' => 'test_settings_app',
      'label' => 'Test Settings App',
      'status' => TRUE,
      'on_init' => 'console.log("init");',
      'on_accept' => 'console.log("accept");',
      'on_decline' => 'console.log("decline");',
    ])->save();

    /** @var \Drupal\klaro\Utility\KlaroHelper $helper */
    $helper = \Drupal::service('klaro.helper');
    $settings = $helper->processDrupalSettings();

    $service = NULL;
    foreach ($settings['config']['services'] as $s) {
      if ($s['name'] === 'test_settings_app') {
        $service = $s;
        break;
      }
    }

    $this->assertNotNull($service, 'Service found in settings.');
    $this->assertSame('console.log("init");', $service['onInit']);
    $this->assertSame('console.log("accept");', $service['onAccept']);
    $this->assertSame('console.log("decline");', $service['onDecline']);
  }

  /**
   * Tests the shipped Google Consent Mode v2 services.
   */
  public function testConsentModeAppsShipped() {
    $gtm = KlaroApp::load('gtm_consent_mode');
    $this->assertNotNull($gtm, 'gtm_consent_mode service is shipped with the module.');
    $this->assertTrue($gtm->isRequired(), 'gtm_consent_mode is required so consent defaults always run.');
    $this->assertStringContainsString("gtag('consent', 'default'", $gtm->onInit());
    $this->assertStringContainsString('opts.consents', $gtm->onAccept());

    $ga = KlaroApp::load('ga_consent_mode');
    $this->assertNotNull($ga, 'ga_consent_mode service is shipped with the module.');
    $this->assertStringContainsString("'analytics_storage': 'granted'", $ga->onAccept());
    $this->assertStringContainsString("'analytics_storage': 'denied'", $ga->onDecline());

    $ads = KlaroApp::load('google_ads_consent_mode');
    $this->assertNotNull($ads, 'google_ads_consent_mode service is shipped with the module.');
    $this->assertStringContainsString("'ad_storage': 'granted'", $ads->onAccept());
    $this->assertStringContainsString("'ad_storage': 'denied'", $ads->onDecline());
  }

  /**
   * Tests klaro_update_10017 sets empty defaults on existing entities.
   */
  public function testUpdateHook10017() {
    // Create an app and save it normally (it gets defaults).
    $app = KlaroApp::create([
      'id' => 'pre_update_app',
      'label' => 'Pre-Update App',
      'status' => TRUE,
    ]);
    $app->save();

    // Simulate a pre-existing entity by directly writing config without the
    // new fields, then verify the update hook saves them explicitly.
    $config = \Drupal::configFactory()->getEditable('klaro.klaro_app.pre_update_app');
    $config->clear('on_init')->clear('on_accept')->clear('on_decline')->save();

    // Confirm the fields are missing from stored config.
    $this->assertNull(\Drupal::configFactory()->get('klaro.klaro_app.pre_update_app')->get('on_init'));

    // Load the update hook file and run it.
    require_once \Drupal::root() . '/' . \Drupal::moduleHandler()->getModule('klaro')->getPath() . '/klaro.install';
    klaro_update_10017();

    // Verify the fields are now explicitly set to empty string.
    $updated_config = \Drupal::configFactory()->get('klaro.klaro_app.pre_update_app');
    $this->assertSame('', $updated_config->get('on_init'), 'on_init set to empty string by update hook.');
    $this->assertSame('', $updated_config->get('on_accept'), 'on_accept set to empty string by update hook.');
    $this->assertSame('', $updated_config->get('on_decline'), 'on_decline set to empty string by update hook.');
  }

}
