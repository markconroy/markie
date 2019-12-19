<?php

namespace Drupal\Tests\xmlsitemap\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;

/**
 * Base class for xmlsitemap kernel tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'xmlsitemap',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['xmlsitemap']);
    $this->installSchema('xmlsitemap', ['xmlsitemap']);

    // Install hooks are not run with kernel tests.
    xmlsitemap_install();
    $this->assertDirectoryExists('public://xmlsitemap');
  }

  /**
   * {@inheritdoc}
   *
   * This method is only available in PHPUnit 6+.
   */
  public static function assertDirectoryExists($directory, $message = '') {
    if (method_exists(get_parent_class(), 'assertDirectoryExists')) {
      parent::assertDirectoryExists($directory, $message);
    }
    else {
      parent::assertTrue(is_dir($directory), $message);
    }
  }

  /**
   * {@inheritdoc}
   *
   * This method is only available in PHPUnit 6+.
   */
  public static function assertDirectoryNotExists($directory, $message = '') {
    if (method_exists(get_parent_class(), 'assertDirectoryNotExists')) {
      parent::assertDirectoryNotExists($directory, $message);
    }
    else {
      parent::assertFalse(is_dir($directory), $message);
    }
  }

}
