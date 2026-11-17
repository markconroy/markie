<?php

namespace Drupal\Tests\key\Kernel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Plugin\KeyTypeMultivalueInterface;

/**
 * Defines a test for key multi-values.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
class KeyMultiValueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'key_test',
    'system',
    'user',
  ];

  /**
   * Tests that multi-value keys get serialized.
   */
  public function testMultiValuesAreSerialized() {
    $key = Key::create([
      'id' => 'multi',
      'label' => 'Multi',
      'description' => 'Multi-value key',
      'key_type' => 'key_test_multi',
      'key_type_settings' => [],
      'key_provider' => 'key_test_state',
      'key_provider_settings' => [
        'state_key' => 'test_multivalue',
      ],
      'key_input' => 'key_test_multi',
      'key_test_multi_settings' => [
        'first' => 'something',
        'second' => 'else',
      ],
    ]);
    $key->setKeyValue([
      'first' => 'woof',
      'second' => 'bark',
    ]);
    $key->save();
    $this->assertEquals(json_encode(['first' => 'woof', 'second' => 'bark']), \Drupal::state()->get('key_test:test_multivalue'));
  }

  /**
   * Tests reading a multivalue key whose provider has no stored value.
   *
   * @see https://www.drupal.org/project/key/issues/3550470
   */
  public function testEmptyMultiValueKeyValues() {
    $key = Key::create([
      'id' => 'empty_multi',
      'label' => 'Empty multi',
      'key_type' => 'authentication_multivalue',
      'key_provider' => 'key_test_state',
      'key_provider_settings' => [
        'state_key' => 'test_empty_multivalue',
      ],
      'key_input' => 'none',
    ]);
    $key->save();

    // The state provider returns NULL, which must not reach json_decode().
    // Turn PHP deprecations into exceptions so the test fails if it does.
    set_error_handler(static function (int $errno, string $errstr): never {
      throw new \ErrorException($errstr, 0, $errno);
    }, E_DEPRECATED);
    try {
      $this->assertNull($key->getKeyValue());
      $this->assertNull($key->getKeyValues());
    }
    finally {
      restore_error_handler();
    }

    // Invalid JSON still unserializes to NULL, so validation can reject it.
    $key_type = $key->getKeyType();
    $this->assertInstanceOf(KeyTypeMultivalueInterface::class, $key_type);
    $this->assertNull($key_type->unserialize(''));
    $this->assertNull($key_type->unserialize('not json'));
    $this->assertSame(['username' => 'a', 'password' => 'b'], $key_type->unserialize('{"username":"a","password":"b"}'));
  }

  /**
   * Tests that an array value is rejected for a single-value key type.
   */
  public function testArrayValueRejectedForSingleValueKey() {
    $key = Key::create([
      'id' => 'single',
      'label' => 'Single',
      'key_type' => 'authentication',
      'key_provider' => 'key_test_state',
      'key_provider_settings' => [
        'state_key' => 'test_single_value',
      ],
      'key_input' => 'text_field',
    ]);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('The key type "authentication" of key "single" does not support multiple values, so the key value must be a string.');
    $key->setKeyValue(['first' => 'woof']);
  }

  /**
   * Tests that a string value is stored as-is for a single-value key type.
   */
  public function testStringValueForSingleValueKey() {
    $key = Key::create([
      'id' => 'single',
      'label' => 'Single',
      'key_type' => 'authentication',
      'key_provider' => 'key_test_state',
      'key_provider_settings' => [
        'state_key' => 'test_single_value',
      ],
      'key_input' => 'text_field',
    ]);
    $key->setKeyValue('woof');
    $key->save();
    $this->assertSame('woof', \Drupal::state()->get('key_test:test_single_value'));
  }

}
