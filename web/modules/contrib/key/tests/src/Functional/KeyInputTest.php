<?php

namespace Drupal\Tests\key\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\Tests\BrowserTestBase;
use Drupal\key\Entity\Key;

/**
 * Tests input of keys.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
class KeyInputTest extends BrowserTestBase {

  use KeyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key'];

  /**
   * A user with the 'administer keys' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer keys']);
  }

  /**
   * Tests the a long key input.
   */
  public function testLongKey() {
    $this->drupalLogin($this->adminUser);
    $assert_session = $this->assertSession();

    // Add a key with a 4000 characters.
    $this->drupalGet('admin/config/system/keys/add');

    $edit = [
      'id' => 'testing_key',
      'label' => 'Testing Key',
      'key_type' => 'authentication',
      'key_input_settings[key_value]' => str_pad('', 4000, 'z'),
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains(sprintf('The key %s has been added.', $edit['label']));

    // Go to the Key page.
    $this->drupalGet('admin/config/system/keys/manage/testing_key');
    $assert_session->statusCodeEquals(200);
  }

  /**
   * Tests that a textarea key input accepts values longer than 4096 chars.
   */
  public function testLongTextareaKey() {
    $this->drupalLogin($this->adminUser);

    Key::create([
      'id' => 'testing_key',
      'label' => 'Testing Key',
      'key_type' => 'authentication_multivalue',
      'key_provider' => 'config',
      'key_input' => 'textarea_field',
    ])->save();
    $long_value = json_encode(['certificate' => str_repeat('z', 6000)]);

    $this->drupalGet('admin/config/system/keys/manage/testing_key');
    $this->submitForm([], 'Edit');
    $this->assertSession()->elementExists('css', 'textarea[name="key_input_settings[key_value]"]');
    $this->assertSession()->elementNotExists('css', 'textarea[name="key_input_settings[key_value]"][maxlength]');
    $this->submitForm(['key_input_settings[key_value]' => $long_value], 'Save');
    $this->assertSession()->pageTextNotContains('cannot be longer than');
    $this->assertSession()->pageTextContains('The key Testing Key has been updated.');

    $key = Key::load('testing_key');
    $this->assertSame($long_value, $key->getKeyValue());
  }

  /**
   * Tests the key value description for multivalue key types.
   */
  public function testMultivalueKeyValueDescription() {
    $this->drupalLogin($this->adminUser);
    $assert_session = $this->assertSession();

    // A single-value key type does not describe a JSON format.
    $this->createTestKey('single_key', 'authentication');
    $this->drupalGet('admin/config/system/keys/manage/single_key');
    $this->submitForm([], 'Edit');
    $assert_session->fieldExists('key_input_settings[key_value]');
    $assert_session->pageTextNotContains('Enter the key value as a JSON object');

    // A multivalue key type describes its expected attributes.
    $this->createTestKey('user_password_key', 'user_password');
    $this->drupalGet('admin/config/system/keys/manage/user_password_key');
    $this->submitForm([], 'Edit');
    $assert_session->pageTextContains('Enter the key value as a JSON object with the following attributes: username, password. Example: {"username":"...","password":"..."}');

    // The example in the key type description is valid input.
    $example = '{"username": "myusername", "password": "mysecretpassword"}';
    $assert_session->pageTextContains('Example: ' . $example);
    $this->submitForm(['key_input_settings[key_value]' => $example], 'Save');
    $assert_session->pageTextNotContains('The key value does not contain valid JSON.');
    $assert_session->pageTextContains('The key Test key has been updated.');

    $key = \Drupal::entityTypeManager()->getStorage('key')->loadUnchanged('user_password_key');
    $this->assertSame(['username' => 'myusername', 'password' => 'mysecretpassword'], $key->getKeyValues());
  }

}
