<?php

namespace Drupal\Tests\ai_logging\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests enabling AI Logging.
 *
 * @group ai_logging
 */
class InstallAiSearchTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      \Drupal::service('module_installer')->install(['ai_logging']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_logging'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

}
