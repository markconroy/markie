<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests validation of an empty key value submitted through the key edit form.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyEditFormEmptyValueTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'key_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A 256-bit key value.
   */
  private const KEY_VALUE = 'abcdefghijklmnopqrstuvwxyz012345';

  /**
   * Tests clearing an existing value validates the cleared value.
   *
   * Clearing the value on the edit form writes the empty value to the key
   * provider, so the empty value, not the provider's current value, is the
   * one that must pass validation.
   */
  public function testClearedValueIsValidated(): void {
    $this->container->get('state')->set('key_test:', self::KEY_VALUE);
    Key::create([
      'id' => 'empty_value',
      'label' => 'Empty value',
      'key_type' => 'encryption',
      'key_type_settings' => ['key_size' => 256],
      'key_provider' => 'key_test_state',
      'key_provider_settings' => ['state_key' => ''],
      'key_input' => 'text_field',
      'key_input_settings' => ['base64_encoded' => FALSE],
    ])->save();

    $this->drupalLogin($this->drupalCreateUser(['administer keys']));
    $this->drupalGet('admin/config/system/keys/manage/empty_value');
    $this->submitForm([], 'Edit');

    // Saving the obscured value unchanged keeps the provider's value.
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The key Empty value has been updated.');
    $this->container->get('state')->resetCache();
    $this->assertSame(self::KEY_VALUE, $this->container->get('state')->get('key_test:'));

    $this->drupalGet('admin/config/system/keys/manage/empty_value');
    $this->submitForm([], 'Edit');
    $this->submitForm(['key_input_settings[key_value]' => ''], 'Save');
    $this->assertSession()->pageTextContains('The selected key size does not match the actual size of the key.');
    $this->container->get('state')->resetCache();
    $this->assertSame(self::KEY_VALUE, $this->container->get('state')->get('key_test:'));
  }

}
