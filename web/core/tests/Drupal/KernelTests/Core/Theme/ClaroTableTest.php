<?php

namespace Drupal\KernelTests\Core\Theme;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests Claro specific table functionality.
 *
 * @group Theme
 */
class ClaroTableTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Confirm that Claro tables override use of the `sticky-enabled` class.
   */
  public function testThemeTableStickyHeaders() {
    // Enable the Claro theme.
    \Drupal::service('theme_installer')->install(['claro']);
    $this->config('system.theme')->set('default', 'claro')->save();
    $header = ['one', 'two', 'three'];
    $rows = [[1, 2, 3], [4, 5, 6], [7, 8, 9]];
    $table = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#sticky' => TRUE,
    ];
    $this->render($table);

    // Confirm that position-sticky is used instead of sticky-enabled.
    $this->assertNoRaw('sticky-enabled');
    $this->assertRaw('position-sticky');
  }

}
