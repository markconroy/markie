<?php

namespace Drupal\Tests\upgrade_status\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Defines shared functions used by some of the functional tests.
 */
abstract class UpgradeStatusTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = [
    'upgrade_status',
    'upgrade_status_test_error',
    'upgrade_status_test_no_error',
    'upgrade_status_test_submodules_a',
    'upgrade_status_test_submodules_with_error',
    'upgrade_status_test_contrib_error',
    'upgrade_status_test_contrib_no_error',
    'upgrade_status_test_theme_functions',
    'upgrade_status_test_twig',
    'upgrade_status_test_library',
    'upgrade_status_test_library_exception',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->container->get('theme_installer')->install(['upgrade_status_test_theme']);
  }

  /**
   * Perform a full scan on all test modules.
   */
  protected function runFullScan() {
    $edit = [
      'contrib[data][installed][upgrade_status]' => TRUE,
      'custom[data][installed][upgrade_status_test_error]' => TRUE,
      'custom[data][installed][upgrade_status_test_no_error]' => TRUE,
      'custom[data][installed][upgrade_status_test_submodules]' => TRUE,
      'custom[data][installed][upgrade_status_test_submodules_with_error]' => TRUE,
      'custom[data][installed][upgrade_status_test_twig]' => TRUE,
      'custom[data][installed][upgrade_status_test_theme]' => TRUE,
      'custom[data][installed][upgrade_status_test_theme_functions]' => TRUE,
      'custom[data][installed][upgrade_status_test_library]' => TRUE,
      'custom[data][installed][upgrade_status_test_library_exception]' => TRUE,
      'contrib[data][installed][upgrade_status_test_contrib_error]' => TRUE,
      'contrib[data][installed][upgrade_status_test_contrib_no_error]' => TRUE,
    ];
    $this->drupalPostForm('admin/reports/upgrade-status', $edit, 'Scan selected');
  }

}
