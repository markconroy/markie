<?php

namespace Drupal\Tests\klaro\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Asset\AttachedAssetsInterface;
use Drupal\klaro\Entity\KlaroApp;
use Drupal\user\Entity\Role;

/**
 * Test klaro_js_alter behavior.
 *
 * @group klaro
 */
class JsAlterTest extends KernelTestBase {

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

    // Grant 'use klaro' permission to anonymous users.
    $role = Role::load('anonymous');
    $role->grantPermission('use klaro');
    $role->save();
  }

  /**
   * Test all combinations of opt_out and required flags.
   */
  public function testOptOutRequiredCombinations() {
    // Create apps for all "required" / "opt_out" combinations:
    // Combination 1: opt_out = FALSE, required = FALSE:
    KlaroApp::create([
      'id' => 'normal_app',
      'label' => 'Normal App',
      'javascripts' => ['example.com/normal.js'],
      'opt_out' => FALSE,
      'required' => FALSE,
      'status' => TRUE,
    ])->save();

    // Combination 2: opt_out = FALSE, required = TRUE:
    KlaroApp::create([
      'id' => 'required_only_app',
      'label' => 'Required Only App',
      'javascripts' => ['example.com/required-only.js'],
      'opt_out' => FALSE,
      'required' => TRUE,
      'status' => TRUE,
    ])->save();

    // Combination 3: opt_out = TRUE, required = FALSE:
    KlaroApp::create([
      'id' => 'opt_out_only_app',
      'label' => 'Opt Out Only App',
      'javascripts' => ['example.com/opt-out-only.js'],
      'opt_out' => TRUE,
      'required' => FALSE,
      'status' => TRUE,
    ])->save();

    // Combination 4: This klaro app should skip script processing
    // (opt_out = TRUE, required = TRUE):
    KlaroApp::create([
      'id' => 'opt_out_and_required_app',
      'label' => 'Opt Out And Required App',
      'javascripts' => ['example.com/opt-out-and-required.js'],
      'opt_out' => TRUE,
      'required' => TRUE,
      'status' => TRUE,
    ])->save();

    // Mock js scripts:
    $javascript = [
      'https://example.com/normal.js' => [
        'type' => 'file',
        'data' => 'https://example.com/normal.js',
        'preprocess' => TRUE,
      ],
      'https://example.com/required-only.js' => [
        'type' => 'file',
        'data' => 'https://example.com/required-only.js',
        'preprocess' => TRUE,
      ],
      'https://example.com/opt-out-only.js' => [
        'type' => 'file',
        'data' => 'https://example.com/opt-out-only.js',
        'preprocess' => TRUE,
      ],
      'https://example.com/opt-out-and-required.js' => [
        'type' => 'file',
        'data' => 'https://example.com/opt-out-and-required.js',
        'preprocess' => TRUE,
      ],
    ];

    // Mock Assets.
    $assets = $this->createMock(AttachedAssetsInterface::class);

    // Manually call the alter hook:
    klaro_js_alter($javascript, $assets);

    // Assert the js scripts (preprocess differs and klaro key might not be set)
    // Assertions for "opt_out"=FALSE, "required"=FALSE:
    $this->assertFalse($javascript['https://example.com/normal.js']['preprocess'],
      'Normal app script should have preprocess set to FALSE.');
    $this->assertArrayHasKey('klaro', $javascript['https://example.com/normal.js'],
      'Normal app script should have klaro key.');
    $this->assertEquals('normal_app', $javascript['https://example.com/normal.js']['klaro'],
      'Normal app script should have correct klaro app id.');

    // Assertions for opt_out=FALSE, required=TRUE:
    $this->assertFalse($javascript['https://example.com/required-only.js']['preprocess'],
      'Required-only app script should have preprocess set to FALSE.');
    $this->assertArrayHasKey('klaro', $javascript['https://example.com/required-only.js'],
      'Required-only app script should have klaro key.');
    $this->assertEquals('required_only_app', $javascript['https://example.com/required-only.js']['klaro'],
      'Required-only app script should have correct klaro app id.');

    // Assertions for opt_out=TRUE, required=FALSE:
    $this->assertFalse($javascript['https://example.com/opt-out-only.js']['preprocess'],
      'Opt-out-only app script should have preprocess set to FALSE.');
    $this->assertArrayHasKey('klaro', $javascript['https://example.com/opt-out-only.js'],
      'Opt-out-only app script should have klaro key.');
    $this->assertEquals('opt_out_only_app', $javascript['https://example.com/opt-out-only.js']['klaro'],
      'Opt-out-only app script should have correct klaro app id.');

    // Assertions for opt_out=TRUE, required=TRUE:
    $this->assertTrue($javascript['https://example.com/opt-out-and-required.js']['preprocess'],
      'Opt-out AND required app script should keep preprocess as TRUE (not blocked server-side).');
    $this->assertArrayNotHasKey('klaro', $javascript['https://example.com/opt-out-and-required.js'],
      'Opt-out AND required app script should NOT have klaro key (not blocked server-side).');
  }

}
