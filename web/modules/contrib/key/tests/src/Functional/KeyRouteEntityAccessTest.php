<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that key routes respect entity access, not just permissions.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyRouteEntityAccessTest extends BrowserTestBase {

  use KeyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'key_access_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that entity access hooks can deny access to admin routes.
   */
  public function testEntityAccessHooksAreRespected(): void {
    $this->createTestKey('key_allowed');
    $this->createTestKey('key_denied');
    $this->createTestKeyConfigOverride('override_allowed', 'key_allowed');
    $this->createTestKeyConfigOverride('override_denied', 'key_allowed');

    $this->drupalLogin($this->drupalCreateUser([
      'administer keys',
      'administer key configuration overrides',
    ]));

    $routes = [
      'entity.key.add_form' => [],
      'entity.key.edit_form' => ['key' => 'key_allowed'],
      'entity.key.delete_form' => ['key' => 'key_allowed'],
      'entity.key_config_override.add_form' => [],
      'entity.key_config_override.delete_form' => ['key_config_override' => 'override_allowed'],
    ];
    $denied_routes = [
      'entity.key.edit_form' => ['key' => 'key_denied'],
      'entity.key.delete_form' => ['key' => 'key_denied'],
      'entity.key_config_override.delete_form' => ['key_config_override' => 'override_denied'],
    ];

    // With no restrictions, the permission alone grants access.
    $this->routeAccessTest($routes + $denied_routes, 200);

    \Drupal::state()->set('key_access_test.forbidden', ['key_denied', 'override_denied']);
    \Drupal::state()->set('key_access_test.forbidden_create', ['key', 'key_config_override']);

    $this->routeAccessTest($denied_routes, 403);
    $this->routeAccessTest([
      'entity.key.add_form' => [],
      'entity.key_config_override.add_form' => [],
    ], 403);
    // Entities that are not restricted remain accessible.
    $this->routeAccessTest([
      'entity.key.edit_form' => ['key' => 'key_allowed'],
      'entity.key.delete_form' => ['key' => 'key_allowed'],
      'entity.key_config_override.delete_form' => ['key_config_override' => 'override_allowed'],
    ], 200);
  }

}
