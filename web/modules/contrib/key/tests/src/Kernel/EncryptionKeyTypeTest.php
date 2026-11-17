<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Plugin\KeyType\EncryptionKeyType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Encryption key type.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class EncryptionKeyTypeTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system',
    'user',
  ];

  /**
   * Tests that an invalid custom key size is a validation error, not a fatal.
   *
   * @param string $key_size
   *   The custom key size entered in the form.
   *
   * @dataProvider providerInvalidKeySize
   */
  #[DataProvider('providerInvalidKeySize')]
  public function testInvalidCustomKeySize(string $key_size): void {
    $form_state = $this->submitAddForm($key_size);

    $errors = $form_state->getErrors();
    $this->assertArrayHasKey('key_size_other_value', $errors);
    // The invalid size is not also compared with the key value.
    $this->assertArrayNotHasKey('key_size', $errors);
    $this->assertNull(Key::load('encryption_key'));
  }

  /**
   * Data provider for testInvalidCustomKeySize().
   *
   * @return array
   *   Custom key sizes that are not a positive multiple of 8.
   */
  public static function providerInvalidKeySize(): array {
    return [
      'non-numeric' => ['abc'],
      'numeric prefix' => ['128abc'],
      'empty' => [''],
      'zero' => ['0'],
      'negative' => ['-128'],
      'decimal' => ['128.5'],
      'not a whole number of bytes' => ['100'],
    ];
  }

  /**
   * Tests validation of key sizes and key values.
   */
  public function testValidateKeySize(): void {
    /** @var \Drupal\key\Plugin\KeyType\EncryptionKeyType $key_type */
    $key_type = $this->container->get('plugin.manager.key.key_type')->createInstance('encryption');
    $form = [];

    // A custom key size replaces the 'other' option.
    $form_state = (new FormState())->setValues([
      'key_size' => 'other',
      'key_size_other_value' => '192',
    ]);
    $key_type->validateConfigurationForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertSame(['key_size' => 192], $form_state->getValues());

    $key_type->validateKeyValue($form, $form_state, str_repeat('a', 24));
    $this->assertSame([], $form_state->getErrors());
    $key_type->validateKeyValue($form, $form_state, str_repeat('a', 16));
    $this->assertArrayHasKey('key_size', $form_state->getErrors());

    // A predefined key size is unchanged.
    $form_state = (new FormState())->setValues([
      'key_size' => '256',
      'key_size_other_value' => '',
    ]);
    $key_type->validateConfigurationForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $this->assertSame(['key_size' => '256'], $form_state->getValues());
    $key_type->validateKeyValue($form, $form_state, str_repeat('a', 32));
    $this->assertSame([], $form_state->getErrors());

    // An invalid key size is not used to validate the key value.
    $form_state = (new FormState())->setValues(['key_size' => 'abc']);
    $key_type->validateKeyValue($form, $form_state, str_repeat('a', 32));
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Tests generateKeyValue() with configured and submitted key sizes.
   */
  public function testGenerateKeyValue(): void {
    $this->assertSame(32, strlen(EncryptionKeyType::generateKeyValue(['key_size' => 256])));
    $this->assertSame(32, strlen(EncryptionKeyType::generateKeyValue(['key_size' => '256'])));
    $this->assertSame(32, strlen(EncryptionKeyType::generateKeyValue([])));
    $this->assertSame(24, strlen(EncryptionKeyType::generateKeyValue([
      'key_size' => 'other',
      'key_size_other_value' => '192',
    ])));
    // An invalid size falls back to the default, rather than failing.
    $this->assertSame(32, strlen(EncryptionKeyType::generateKeyValue([
      'key_size' => 'other',
      'key_size_other_value' => 'abc',
    ])));
    $this->assertSame(32, strlen(EncryptionKeyType::generateKeyValue(['key_size' => 'abc'])));
  }

  /**
   * Submits the key add form for an Encryption key with a custom key size.
   *
   * @param string $key_size
   *   The custom key size, in bits.
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state.
   */
  private function submitAddForm(string $key_size): FormState {
    $form_object = $this->container->get('entity_type.manager')->getFormObject('key', 'add');
    $form_object->setEntity(Key::create([
      'key_type' => 'encryption',
      'key_type_settings' => ['key_size' => 128],
      'key_provider' => 'config',
      'key_input' => 'text_field',
    ]));
    $form_state = (new FormState())->setValues([
      'id' => 'encryption_key',
      'label' => 'Encryption key',
      'key_type' => 'encryption',
      'key_type_settings' => [
        'key_size' => 'other',
        'key_size_other_value' => $key_size,
      ],
      'key_provider' => 'config',
      'op' => 'Save',
    ]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);
    return $form_state;
  }

}
