<?php

namespace Drupal\Tests\xmlsitemap\Kernel;

/**
 * Tests directory functions.
 *
 * @group xmlsitemap
 */
class DirectoryTest extends KernelTestBase {

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
   * Test xmlsitemap_clear_directory().
   *
   * @covers ::xmlsitemap_get_directory
   * @covers ::xmlsitemap_clear_directory
   * @covers ::_xmlsitemap_delete_recursive
   */
  public function testClearDirectory() {
    /** @var \Drupal\Core\File\FileSystemInterface $fileSystem */
    $fileSystem = $this->container->get('file_system');

    // Set up a couple more directories and files.
    $directory = 'public://not-xmlsitemap';
    $fileSystem->prepareDirectory($directory, $fileSystem::CREATE_DIRECTORY | $fileSystem::MODIFY_PERMISSIONS);
    $directory = 'public://xmlsitemap/test';
    $fileSystem->prepareDirectory($directory, $fileSystem::CREATE_DIRECTORY | $fileSystem::MODIFY_PERMISSIONS);
    $fileSystem->saveData('File unrelated to XML sitemap', 'public://not-xmlsitemap/file.txt');
    $fileSystem->saveData('File unrelated to XML sitemap', 'public://file.txt');
    $fileSystem->saveData('Test contents', 'public://xmlsitemap/test/index.xml');

    // Set the directory to an empty value.
    \Drupal::configFactory()->getEditable('xmlsitemap.settings')->clear('path')->save();
    drupal_static_reset('xmlsitemap_get_directory');
    $result = xmlsitemap_clear_directory(NULL, TRUE);

    // Test that nothing was deleted.
    $this->assertFileExists('public://xmlsitemap/test/index.xml');
    $this->assertDirectoryExists('public://not-xmlsitemap');
    $this->assertFileExists('public://file.txt');
    $this->assertFalse($result);

    // Reset the value back to the default.
    \Drupal::configFactory()->getEditable('xmlsitemap.settings')->set('path', 'xmlsitemap')->save();
    drupal_static_reset('xmlsitemap_get_directory');
    $result = xmlsitemap_clear_directory(NULL, TRUE);

    // Test that only the xmlsitemap directory was deleted.
    $this->assertDirectoryNotExists('public://xmlsitemap/test');
    $this->assertDirectoryExists('public://not-xmlsitemap');
    $this->assertFileExists('public://file.txt');
    $this->assertTrue($result);
  }

}
