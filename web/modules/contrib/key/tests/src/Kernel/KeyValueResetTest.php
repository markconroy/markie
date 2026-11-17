<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\KeyInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests resetting cached key values through the interface.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyValueResetTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system',
  ];

  /**
   * Tests that $reset re-reads the value from the key provider.
   */
  public function testResetRetrievesValueFromProvider(): void {
    \Drupal::state()->set('key_reset_test', 'first');
    $key = Key::create([
      'id' => 'reset_test',
      'label' => 'Reset test',
      'key_type' => 'authentication',
      'key_provider' => 'state',
      'key_provider_settings' => [
        'state_key' => 'key_reset_test',
      ],
      'key_input' => 'none',
    ]);
    $key->save();

    $this->assertKeyValueReset($key);
  }

  /**
   * Asserts the reset behavior of a key typed only by its interface.
   *
   * @param \Drupal\key\KeyInterface $key
   *   A key whose provider reads the 'key_reset_test' state value.
   */
  private function assertKeyValueReset(KeyInterface $key): void {
    $this->assertSame('first', $key->getKeyValue());
    $this->assertSame(['first'], $key->getKeyValues());

    \Drupal::state()->set('key_reset_test', 'second');

    // Without a reset, the value cached for the request is returned.
    $this->assertSame('first', $key->getKeyValue());
    $this->assertSame(['first'], $key->getKeyValues());

    // With a reset, the value is retrieved from the provider again.
    $this->assertSame('second', $key->getKeyValue(TRUE));
    \Drupal::state()->set('key_reset_test', 'third');
    $this->assertSame(['third'], $key->getKeyValues(TRUE));
  }

}
