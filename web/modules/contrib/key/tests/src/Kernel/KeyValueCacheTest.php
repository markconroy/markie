<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the per-request key value cache is invalidated.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyValueCacheTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system',
    'user',
  ];

  /**
   * Creates and saves a key that stores its value in configuration.
   *
   * @param string $key_value
   *   The key value.
   *
   * @return \Drupal\key\Entity\Key
   *   The saved key.
   */
  protected function createConfigKey(string $key_value): Key {
    $key = Key::create([
      'id' => 'cached',
      'label' => 'Cached',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_input' => 'text_field',
    ]);
    $key->setKeyValue($key_value);
    $key->save();
    return $key;
  }

  /**
   * Loads the key from storage as a new object.
   *
   * @return \Drupal\key\Entity\Key
   *   The key.
   */
  protected function reloadKey(): Key {
    $key = \Drupal::entityTypeManager()->getStorage('key')->loadUnchanged('cached');
    $this->assertInstanceOf(Key::class, $key);
    return $key;
  }

  /**
   * Tests that saving a new key value invalidates the cached value.
   */
  public function testSaveInvalidatesCachedValue(): void {
    $key = $this->createConfigKey('first');
    $this->assertSame('first', $key->getKeyValue());

    $key->setKeyValue('second');
    $key->save();
    $this->assertSame('second', $key->getKeyValue());
    $this->assertSame(['second'], $key->getKeyValues());
    $this->assertSame('second', $this->reloadKey()->getKeyValue());

    // A value saved through another object for the same key is not masked by
    // the value cached earlier in the request.
    $other = $this->reloadKey();
    $other->setKeyValue('third');
    $other->save();
    $this->assertSame('third', $this->reloadKey()->getKeyValue());
  }

  /**
   * Tests that the value is cached for the request and $reset bypasses it.
   */
  public function testResetBypassesCachedValue(): void {
    $key = $this->createConfigKey('first');
    $this->assertSame('first', $key->getKeyValue());

    // Change the stored value without saving the entity, so nothing
    // invalidates the cached value.
    $this->config('key.key.cached')
      ->set('key_provider_settings.key_value', 'changed')
      ->save();
    $key = $this->reloadKey();
    $this->assertSame('changed', $key->getKeyProvider()->getKeyValue($key));
    $this->assertSame('first', $key->getKeyValue());
    $this->assertSame('changed', $key->getKeyValue(TRUE));
    $this->assertSame('changed', $key->getKeyValue());
  }

  /**
   * Tests that a value that has not been saved is not served from the cache.
   */
  public function testUnsavedValueIsNotCached(): void {
    $key = $this->createConfigKey('first');
    $this->assertSame('first', $key->getKeyValue());

    $key->setKeyValue('unsaved');
    $this->assertSame('first', $this->reloadKey()->getKeyValue());
  }

  /**
   * Tests that deleting a key invalidates the cached value.
   */
  public function testDeleteInvalidatesCachedValue(): void {
    $key = $this->createConfigKey('first');
    $this->assertSame('first', $key->getKeyValue());
    $key->delete();

    $key = $this->createConfigKey('recreated');
    $this->assertSame('recreated', $key->getKeyValue());
  }

}
