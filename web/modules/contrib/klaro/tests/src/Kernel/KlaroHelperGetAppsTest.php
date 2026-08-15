<?php

namespace Drupal\Tests\klaro\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\klaro\Entity\KlaroApp;

/**
 * Tests KlaroHelper::getApps() result caching and parameter awareness.
 *
 * @group klaro
 *
 * @see \Drupal\klaro\Utility\KlaroHelper::getApps()
 */
class KlaroHelperGetAppsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['klaro', 'system', 'user'];

  /**
   * The klaro helper service under test.
   *
   * @var \Drupal\klaro\Utility\KlaroHelper
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['klaro']);
    $this->installEntitySchema('klaro_app');

    // Keep result counts deterministic: block_unknown would append a synthetic
    // "unknown_app" entry to every getApps() result.
    $this->config('klaro.settings')->set('block_unknown', FALSE)->save();

    // Klaro ships example apps (youtube, vimeo, ...) in its default config;
    // remove them so the assertions below work with a known set.
    $storage = $this->container->get('entity_type.manager')->getStorage('klaro_app');
    $storage->delete($storage->loadMultiple());

    // One disabled app, one enabled + required, one enabled + optional.
    KlaroApp::create([
      'id' => 'disabled_app',
      'label' => 'Disabled App',
      'status' => FALSE,
      'required' => FALSE,
    ])->save();
    KlaroApp::create([
      'id' => 'required_app',
      'label' => 'Required App',
      'status' => TRUE,
      'required' => TRUE,
    ])->save();
    KlaroApp::create([
      'id' => 'optional_app',
      'label' => 'Optional App',
      'status' => TRUE,
      'required' => FALSE,
    ])->save();

    $this->helper = $this->container->get('klaro.helper');
  }

  /**
   * Each argument combination must return its own correct set of apps.
   *
   * Guards against a naive cache that ignores the arguments and serves one
   * result for every call.
   */
  public function testGetAppsIsParameterAware(): void {
    // only_enabled = TRUE: both enabled apps, not the disabled one.
    $this->assertEqualsCanonicalizing(
      ['required_app', 'optional_app'],
      array_keys($this->helper->getApps(TRUE)),
      'getApps(TRUE) returns only enabled apps.'
    );

    // only_enabled = FALSE: every app, including the disabled one.
    $this->assertEqualsCanonicalizing(
      ['disabled_app', 'required_app', 'optional_app'],
      array_keys($this->helper->getApps(FALSE)),
      'getApps(FALSE) returns all apps regardless of status.'
    );

    // only_enabled = TRUE, only_not_required = TRUE: enabled, non-required.
    $this->assertEqualsCanonicalizing(
      ['optional_app'],
      array_keys($this->helper->getApps(TRUE, TRUE)),
      'getApps(TRUE, TRUE) returns only enabled, non-required apps.'
    );
  }

  /**
   * Repeated calls with the same arguments are memoized within the instance.
   */
  public function testGetAppsIsMemoizedPerArguments(): void {
    $this->assertCount(2, $this->helper->getApps(TRUE));

    // Add a new enabled app after the first call. A non-cached implementation
    // would pick it up; the memoized one returns the previously loaded set.
    KlaroApp::create([
      'id' => 'late_app',
      'label' => 'Late App',
      'status' => TRUE,
      'required' => FALSE,
    ])->save();

    $this->assertCount(
      2,
      $this->helper->getApps(TRUE),
      'getApps() returns the memoized result for the same arguments within a request.'
    );

    // A different argument combination has its own cache key, so it is computed
    // independently and reflects the current data — proving keys dont collide.
    $this->assertCount(
      4,
      $this->helper->getApps(FALSE),
      'A different argument set is computed independently of the cached one.'
    );
  }

}
