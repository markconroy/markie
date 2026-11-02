<?php

namespace Drupal\Tests\metatag_views\Kernel;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;

/**
 * Tests that loading a view's meta tags restores the current view.
 *
 * @group metatag
 */
class MetatagViewsGetViewTagsTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Contrib modules.
    'token',

    // This module.
    'metatag',
    'metatag_views',
  ];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_view', 'test_field_output'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    // Kernel tests do not run hook_install(), which enables the extender.
    $this->setDisplayExtenders(['metatag_display_extender']);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    unset($GLOBALS['_current_view']);
    parent::tearDown();
  }

  /**
   * Tests that the previous current view is restored.
   */
  public function testCurrentViewIsRestored(): void {
    $current_view = Views::getView('test_view');
    $GLOBALS['_current_view'] = $current_view;

    $view = Views::getView('test_view');
    $this->assertSame([], metatag_views_get_view_tags($view, 'default'));
    $this->assertSame($current_view, $GLOBALS['_current_view']);
    $this->assertSame([], $view->old_view);

    // A second view does not keep the first one as its previous view.
    $second_view = Views::getView('test_field_output');
    metatag_views_get_view_tags($second_view, 'default');
    $this->assertSame($current_view, $GLOBALS['_current_view']);
    $this->assertSame([], $second_view->old_view);
  }

  /**
   * Tests that the current view is restored when the extender is missing.
   */
  public function testCurrentViewIsRestoredWithoutExtender(): void {
    $this->setDisplayExtenders([]);
    $current_view = Views::getView('test_view');
    $GLOBALS['_current_view'] = $current_view;

    $view = Views::getView('test_view');
    $this->assertNull(metatag_views_get_view_tags($view, 'default'));
    $this->assertSame($current_view, $GLOBALS['_current_view']);
    $this->assertSame([], $view->old_view);
  }

  /**
   * Sets the enabled Views display extenders.
   *
   * @param string[] $display_extenders
   *   The display extender plugin IDs.
   */
  protected function setDisplayExtenders(array $display_extenders): void {
    $this->config('views.settings')
      ->set('display_extenders', $display_extenders)
      ->save();
  }

}
