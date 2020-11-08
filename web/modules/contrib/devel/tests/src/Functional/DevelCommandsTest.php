<?php

namespace Drupal\Tests\devel\Functional;

use Drupal\Tests\BrowserTestBase;
use Drush\TestTraits\DrushTestTrait;

/**
 * Note: Drush must be installed. See
 * https://cgit.drupalcode.org/devel/tree/drupalci.yml?h=8.x-2.x and its docs
 * at
 * https://www.drupal.org/drupalorg/docs/drupal-ci/customizing-drupalci-testing-for-projects
 */

/**
 * @coversDefaultClass \Drupal\devel\Commands\DevelCommands
 * @group devel
 */
class DevelCommandsTest extends BrowserTestBase {

  use DrushTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['devel'];

  /**
   * Tests drush commands.
   */
  public function testCommands() {
    $this->drush('devel:token', [], ['format' => 'json']);
    $output = $this->getOutputFromJSON();
    $tokens = array_column($output, 'token');
    $this->assertContains('account-name', $tokens);

    $this->drush('devel:services', [], ['format' => 'json']);
    $output = $this->getOutputFromJSON();
    $this->assertContains('current_user', $output);
  }

}
