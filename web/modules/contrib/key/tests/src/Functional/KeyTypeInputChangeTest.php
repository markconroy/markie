<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Functional;

use Drupal\key\Entity\Key;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests saving a key after its key type changes its key input plugin.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyTypeInputChangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'key_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that an existing key value survives a switch to the Generate input.
   */
  public function testSwitchToGenerateKeepsExistingValue(): void {
    Key::create([
      'id' => 'existing_key',
      'label' => 'Existing key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => 'original-secret-value'],
      'key_input' => 'text_field',
    ])->save();

    $this->switchKeyTypeInput('authentication', 'generate');

    $this->drupalLogin($this->drupalCreateUser(['administer keys']));
    $this->drupalGet('admin/config/system/keys/manage/existing_key');
    $this->submitForm([], 'Edit');
    $this->assertSession()->pageTextContains('The key value has already been generated and will not be changed.');
    $this->submitForm(['label' => 'Renamed key'], 'Save');
    $this->assertSession()->pageTextContains('The key Renamed key has been updated.');

    $key = $this->loadUnchangedKey('existing_key');
    $this->assertSame('Renamed key', $key->label());
    $this->assertSame('original-secret-value', $key->getKeyValue(TRUE));
    $this->assertSame('generate', $key->getKeyInput()->getPluginId());
    $this->assertTrue($key->getKeyInput()->getConfiguration()['generated']);

    // A further save keeps the value too.
    $this->drupalGet('admin/config/system/keys/manage/existing_key');
    $this->submitForm([], 'Edit');
    $this->submitForm(['label' => 'Renamed again'], 'Save');
    $this->assertSame('original-secret-value', $this->loadUnchangedKey('existing_key')->getKeyValue(TRUE));
  }

  /**
   * Changes the key input plugin a key type declares.
   *
   * @param string $key_type_id
   *   The key type plugin ID.
   * @param string $key_input_id
   *   The key input plugin ID the key type should declare.
   */
  protected function switchKeyTypeInput(string $key_type_id, string $key_input_id): void {
    \Drupal::state()->set('key_test.key_type_input', [$key_type_id => $key_input_id]);
    \Drupal::service('plugin.manager.key.key_type')->clearCachedDefinitions();
  }

  /**
   * Loads a key, bypassing the entity cache.
   *
   * @param string $id
   *   The key ID.
   *
   * @return \Drupal\key\Entity\Key
   *   The key.
   */
  protected function loadUnchangedKey(string $id): Key {
    $storage = \Drupal::entityTypeManager()->getStorage('key');
    $storage->resetCache([$id]);
    $key = $storage->load($id);
    $this->assertInstanceOf(Key::class, $key);
    return $key;
  }

}
