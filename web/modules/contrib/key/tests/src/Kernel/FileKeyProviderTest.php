<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the File key provider.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class FileKeyProviderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'system'];

  /**
   * Creates a key that reads its value from the given file location.
   */
  protected function createFileKey(string $file_location, string $id = 'file_key'): Key {
    return Key::create([
      'id' => $id,
      'label' => 'File key',
      'key_type' => 'authentication',
      'key_provider' => 'file',
      'key_provider_settings' => [
        'file_location' => $file_location,
      ],
    ]);
  }

  /**
   * Tests reading a key from a stream wrapper URI.
   */
  public function testStreamWrapperLocation(): void {
    file_put_contents('public://test.key', 'secret');
    $key = $this->createFileKey('public://test.key');
    $this->assertSame('secret', $key->getKeyValue());
  }

  /**
   * Tests reading a key before stream wrappers are registered with PHP.
   *
   * HTTP middlewares with a priority above the kernel pre-handle middleware
   * (such as Shield's) run before stream wrappers are registered with PHP.
   */
  public function testStreamWrapperLocationBeforeRegistration(): void {
    file_put_contents('public://test.key', 'secret');
    $key = $this->createFileKey('public://test.key');

    stream_wrapper_unregister('public');
    try {
      $this->assertNotContains('public', stream_get_wrappers());
      $this->assertSame('secret', $key->getKeyValue());
    }
    finally {
      $this->container->get('stream_wrapper_manager')->register();
    }
  }

  /**
   * Tests reading a key from an absolute path and a missing file.
   */
  public function testAbsolutePathAndMissingFile(): void {
    file_put_contents('public://test.key', 'secret');
    $path = $this->container->get('file_system')->realpath('public://test.key');
    $this->assertSame('secret', $this->createFileKey($path)->getKeyValue());

    $this->assertNull($this->createFileKey('public://missing.key', 'missing_key')->getKeyValue());
  }

}
