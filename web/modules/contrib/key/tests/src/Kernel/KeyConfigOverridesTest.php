<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use Drupal\key\Entity\KeyConfigOverride;
use Drupal\key\KeyConfigOverrides;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the key configuration override service.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyConfigOverridesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key', 'system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    $this->config('system.site')->set('name', 'Original name')->save();
  }

  /**
   * Tests that an override created after an empty lookup is applied.
   *
   * Before the fix, an empty mapping left the service flagged as being inside
   * an override, so it silently stopped applying overrides for the rest of
   * the request.
   */
  public function testOverrideAppliedAfterEmptyMapping(): void {
    // Load configuration while no overrides exist.
    $this->assertSame('Original name', \Drupal::config('system.site')->get('name'));

    $this->createOverride();
    $this->assertSame('Overridden name', \Drupal::config('system.site')->get('name'));

    // Deleting the override removes it within the same request.
    KeyConfigOverride::load('site_name')->delete();
    $this->assertSame('Original name', \Drupal::config('system.site')->get('name'));
  }

  /**
   * Tests that an exception while overriding does not disable the service.
   */
  public function testOverrideRecoversFromException(): void {
    $config_factory = $this->createStub(ConfigFactoryInterface::class);
    $config_factory->method('listAll')
      ->willThrowException(new \RuntimeException('Storage unavailable'));
    $overrides = new KeyConfigOverrides($config_factory, new MemoryBackend(\Drupal::time()));

    try {
      $overrides->loadOverrides(['system.site']);
      $this->fail('Expected exception was not thrown.');
    }
    catch (\RuntimeException) {
    }

    // A second call must reach the storage again rather than returning early.
    $this->expectExceptionMessage('Storage unavailable');
    $overrides->loadOverrides(['system.site']);
  }

  /**
   * Tests that the mapping is built once and cached across requests.
   */
  public function testMappingIsCached(): void {
    $cache = new MemoryBackend(\Drupal::time());

    // An empty mapping is only looked up once per instance.
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('listAll')
      ->with('key.config_override.')
      ->willReturn([]);
    $config_factory->method('loadMultiple')->willReturn([]);
    $overrides = new KeyConfigOverrides($config_factory, $cache);
    $this->assertSame([], $overrides->loadOverrides(['system.site']));
    $this->assertSame([], $overrides->loadOverrides(['system.performance']));

    // A new instance, as in a later request, reuses the cached mapping.
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->never())->method('listAll');
    $overrides = new KeyConfigOverrides($config_factory, $cache);
    $this->assertSame([], $overrides->loadOverrides(['system.site']));

    // Saving an override invalidates the cached mapping.
    $cache->invalidateTags(['config:key_config_override_list']);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->expects($this->once())
      ->method('listAll')
      ->willReturn([]);
    $config_factory->method('loadMultiple')->willReturn([]);
    $overrides = new KeyConfigOverrides($config_factory, $cache);
    $overrides->loadOverrides(['system.site']);
  }

  /**
   * Tests that the persistent mapping cache is invalidated on save and delete.
   */
  public function testMappingCacheInvalidatedOnSave(): void {
    \Drupal::config('system.site')->get('name');
    $this->assertNotFalse(\Drupal::cache('data')->get('key_config_override_mapping'));

    $this->createOverride();
    $this->assertFalse(\Drupal::cache('data')->get('key_config_override_mapping'));
    $this->assertSame('Overridden name', \Drupal::config('system.site')->get('name'));
    $this->assertSame(['system.site' => ['name' => 'site_name_key']], \Drupal::cache('data')->get('key_config_override_mapping')->data);
  }

  /**
   * Creates a key and an override of the site name that uses it.
   */
  protected function createOverride(): void {
    Key::create([
      'id' => 'site_name_key',
      'label' => 'Site name key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => 'Overridden name'],
      'key_input' => 'text_field',
    ])->save();
    KeyConfigOverride::create([
      'id' => 'site_name',
      'label' => 'Site name',
      'config_type' => 'system.simple',
      'config_prefix' => '',
      'config_name' => 'system.site',
      'config_item' => 'name',
      'key_id' => 'site_name_key',
    ])->save();
  }

}
