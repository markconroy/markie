<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests hook_module_implements_alter().
 *
 * @group Module
 *
 * @group legacy
 */
class ModuleImplementsAlterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests hook_module_implements_alter() adding an implementation.
   *
   * @see \Drupal\Core\Extension\ModuleHandler::buildImplementationInfo()
   * @see module_implements_alter_test_module_implements_alter()
   */
  public function testModuleImplementsAlter(): void {

    // Get an instance of the module handler, to observe how it is going to be
    // replaced.
    $module_handler = \Drupal::moduleHandler();

    $this->assertSame(\Drupal::moduleHandler(), $module_handler, 'Module handler instance is still the same.');

    // Install the module_implements_alter_test module.
    \Drupal::service('module_installer')->install(['module_implements_alter_test']);

    // Assert that the \Drupal::moduleHandler() instance has been replaced.
    $this->assertNotSame(\Drupal::moduleHandler(), $module_handler, 'The \Drupal::moduleHandler() instance has been replaced during \Drupal::moduleHandler()->install().');

    // Assert that module_implements_alter_test.module is now included.
    $this->assertTrue(function_exists('test_auto_include'),
      'The file module_implements_alter_test.module was successfully included.');

    $this->assertTrue(\Drupal::moduleHandler()->hasImplementations('module_implements_alter', 'module_implements_alter_test'),
      'module_implements_alter_test implements hook_module_implements_alter().');

    // Assert that module_implements_alter_test.implementations.inc is not included yet.
    $this->assertFalse(function_exists('module_implements_alter_test_altered_test_hook'),
      'The file module_implements_alter_test.implementations.inc is not included yet.');

    // Trigger hook discovery for hook_altered_test_hook().
    // Assert that module_implements_alter_test_module_implements_alter(*, 'altered_test_hook')
    // has added an implementation.
    $this->assertTrue(\Drupal::moduleHandler()->hasImplementations('altered_test_hook', 'module_implements_alter_test'),
      'module_implements_alter_test implements hook_altered_test_hook().');

    // Assert that module_implements_alter_test.implementations.inc was included as part of the process.
    $this->assertTrue(function_exists('module_implements_alter_test_altered_test_hook'),
      'The file module_implements_alter_test.implementations.inc was included.');
  }

}
