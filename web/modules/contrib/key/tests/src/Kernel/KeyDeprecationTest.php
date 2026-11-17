<?php

namespace Drupal\Tests\key\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Plugin\KeyProvider\FileKeyProvider;

/**
 * Tests deprecations for Key module.
 *
 * @group key
 * @group legacy
 */
#[Group('key')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class KeyDeprecationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'key_deprecation_test'];

  /**
   * @covers \Drupal\key\Annotation\KeyProvider
   * @covers \Drupal\key\Plugin\KeyPluginManager::processDefinition
   * @covers \Drupal\key\KeyRepository::getKeysByStorageMethod
   * @covers \Drupal\key\KeyRepository::getKeyNamesAsOptions
   */
  public function testStorageMethodDeprecation(): void {
    $message = "The key provider 'storage_method' definition entry is deprecated in key:1.18.0 and is removed from key:2.0.0. Use the 'tags' definition entry instead. See https://www.drupal.org/node/3364701";
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $this->expectUserDeprecationMessage($message), fn() => $this->expectDeprecation($message));
    $plugin = $this->container->get('plugin.manager.key.key_provider')->createInstance('deprecated_defintion_entries');
    $this->assertSame(['whatever'], $plugin->getPluginDefinition()['tags']);

    $key_repository = $this->container->get('key.repository');
    Key::create(['id' => 'test', 'key_provider' => 'file'])->save();

    $message = 'Drupal\key\KeyRepository::getKeysByStorageMethod() is deprecated in key:1.18.0 and is removed from key:2.0.0. Use self::getKeysByTags() instead. See https://www.drupal.org/node/3364701';
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $this->expectUserDeprecationMessage($message), fn() => $this->expectDeprecation($message));
    $keys = $key_repository->getKeysByStorageMethod('file');
    $this->assertCount(1, $keys);
    $this->assertSame('file', reset($keys)->getKeyProvider()->getPluginId());

    $message = "Passing 'storage_method' as filter to Drupal\key\KeyRepository::getKeyNamesAsOptions() is deprecated in key:1.18.0 and is removed from key:2.0.0. Use the 'tags' filter instead. See https://www.drupal.org/node/3364701";
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $this->expectUserDeprecationMessage($message), fn() => $this->expectDeprecation($message));
    $names = $key_repository->getKeyNamesAsOptions(['storage_method' => 'file']);
    $this->assertCount(1, $names);
    $this->assertArrayHasKey('test', $names);
  }

  /**
   * Tests constructing the File key provider without the file system.
   */
  public function testFileKeyProviderConstructorDeprecation(): void {
    $message = 'Calling Drupal\key\Plugin\KeyProvider\FileKeyProvider::__construct() without the $file_system argument is deprecated in key:1.23.0 and it will be required in key:2.0.0. See https://www.drupal.org/node/3559966';
    DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $this->expectUserDeprecationMessage($message), fn() => $this->expectDeprecation($message));
    $plugin = new FileKeyProvider([], 'file', []);
    $this->assertInstanceOf(FileKeyProvider::class, $plugin);
  }

}
