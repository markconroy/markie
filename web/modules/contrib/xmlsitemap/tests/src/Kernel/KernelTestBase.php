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
  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();

    // This is required to not fail the @covers for global functions.
    // @todo Once xmlsitemap_clear_directory() is refactored to auto-loadable code, remove this require statement.
    require_once __DIR__ . '/../../../xmlsitemap.module';
  }

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

}
