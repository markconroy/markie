<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Entity\KeyConfigOverride;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests key providers that cannot retrieve a key value.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyProviderMissingValueTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'key',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * Tests that the File provider returns NULL for a missing file.
   */
  public function testFileProviderMissingFile(): void {
    $key = $this->createKey('file', [
      'file_location' => $this->siteDirectory . '/does-not-exist.key',
    ]);
    $this->assertNull($key->getKeyValue(TRUE));
  }

  /**
   * Tests that the File provider returns the contents of a readable file.
   */
  public function testFileProviderReadableFile(): void {
    $file = $this->siteDirectory . '/test.key';
    file_put_contents($file, "secret\n");
    $key = $this->createKey('file', [
      'file_location' => $file,
      'strip_line_breaks' => TRUE,
    ]);
    $this->assertSame('secret', $key->getKeyValue(TRUE));
  }

  /**
   * Tests that the Environment provider returns NULL for an unset variable.
   */
  public function testEnvProviderUnsetVariable(): void {
    putenv('KEY_TEST_3356052_UNSET');
    $key = $this->createKey('env', [
      'env_variable' => 'KEY_TEST_3356052_UNSET',
    ]);
    $this->assertNull($key->getKeyValue(TRUE));
  }

  /**
   * Tests that a missing key value does not blank an overridden config item.
   */
  public function testConfigOverrideSkipsMissingValue(): void {
    $this->config('system.site')->set('name', 'Original site name')->save();
    $this->createKey('file', [
      'file_location' => $this->siteDirectory . '/does-not-exist.key',
    ])->save();
    KeyConfigOverride::create([
      'id' => 'site_name',
      'label' => 'Site name',
      'config_type' => 'system.simple',
      'config_prefix' => '',
      'config_name' => 'system.site',
      'config_item' => 'name',
      'key_id' => 'test_key',
    ])->save();
    $this->container->get('kernel')->rebuildContainer();

    $this->assertSame('Original site name', $this->container->get('config.factory')->get('system.site')->get('name'));
  }

  /**
   * Creates an unsaved authentication key using the given provider.
   *
   * @param string $provider
   *   The key provider plugin ID.
   * @param array $settings
   *   The key provider settings.
   *
   * @return \Drupal\key\Entity\Key
   *   The key entity.
   */
  private function createKey(string $provider, array $settings): Key {
    return Key::create([
      'id' => 'test_key',
      'label' => 'Test key',
      'key_type' => 'authentication',
      'key_provider' => $provider,
      'key_provider_settings' => $settings,
      'key_input' => 'none',
    ]);
  }

}
