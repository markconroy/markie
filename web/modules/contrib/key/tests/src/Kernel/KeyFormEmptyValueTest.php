<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validation of an empty key value submitted through the key add form.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyFormEmptyValueTest extends KernelTestBase {

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
   * A 256-bit key value.
   */
  private const KEY_VALUE = 'abcdefghijklmnopqrstuvwxyz012345';

  /**
   * Tests an empty value on the add form uses the key provider's value.
   *
   * The key_test_state provider accepts a key value but does not require one,
   * so an empty submitted value leaves the existing provider value in place.
   * That value is the one the key type must validate.
   */
  public function testAddFormEmptyValueUsesProviderValue(): void {
    $this->container->get('state')->set('key_test:', self::KEY_VALUE);

    $key = Key::create($this->keyValues());
    $form_state = $this->submitKeyForm($key, 'key_test_state', ['state_key' => '']);

    $this->assertSame([], $form_state->getErrors());
    $saved = Key::load('empty_value');
    $this->assertNotNull($saved);
    $this->assertSame(self::KEY_VALUE, $saved->getKeyValue(TRUE));
  }

  /**
   * Tests an empty value on the add form is still validated.
   *
   * The config provider has no value of its own until one is submitted, so
   * the empty value is the one the key type must validate.
   */
  public function testAddFormEmptyValueWithConfigProvider(): void {
    $values = ['key_provider' => 'config', 'key_provider_settings' => ['base64_encoded' => FALSE]];
    $key = Key::create($values + $this->keyValues());
    $form_state = $this->submitKeyForm($key, $values['key_provider'], ['base64_encoded' => 0]);

    $this->assertArrayHasKey('key_size', $form_state->getErrors());
    $this->assertNull(Key::load('empty_value'));
  }

  /**
   * Returns the values for a 256-bit encryption key using the state provider.
   *
   * @return array
   *   The key entity values.
   */
  private function keyValues(): array {
    return [
      'id' => 'empty_value',
      'label' => 'Empty value',
      'key_type' => 'encryption',
      'key_type_settings' => ['key_size' => 256],
      'key_provider' => 'key_test_state',
      'key_provider_settings' => ['state_key' => ''],
      'key_input' => 'text_field',
    ];
  }

  /**
   * Submits the key add form with an empty key value.
   *
   * @param \Drupal\key\Entity\Key $key
   *   The key entity.
   * @param string $key_provider
   *   The key provider plugin ID.
   * @param array $key_provider_settings
   *   The submitted key provider settings.
   *
   * @return \Drupal\Core\Form\FormState
   *   The submitted form state.
   */
  private function submitKeyForm(Key $key, string $key_provider, array $key_provider_settings): FormState {
    $form_object = $this->container->get('entity_type.manager')->getFormObject('key', 'add');
    $form_object->setEntity($key);

    $form_state = new FormState();
    $form_state->setValues([
      'id' => 'empty_value',
      'label' => 'Empty value',
      'key_type' => 'encryption',
      'key_type_settings' => ['key_size' => '256', 'key_size_other_value' => ''],
      'key_provider' => $key_provider,
      'key_provider_settings' => $key_provider_settings,
      'key_input_settings' => ['key_value' => ''],
      'op' => 'Save',
    ]);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    return $form_state;
  }

}
