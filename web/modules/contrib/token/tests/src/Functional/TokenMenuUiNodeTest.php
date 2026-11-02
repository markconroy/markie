<?php

namespace Drupal\Tests\token\Functional;

use Drupal\Tests\menu_ui\Functional\MenuUiNodeTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Menu UI and Content Moderation integration.
 *
 * @group token
 */
#[Group('token')]
#[RunTestsInSeparateProcesses]
class TokenMenuUiNodeTest extends MenuUiNodeTest {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['token'];

}
