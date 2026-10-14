<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests enabling ai_translate and its dependencies.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
class InstallAiTranslateTest extends KernelTestBase {

  /**
   * Modules to enable before running the tests.
   *
   * @var array
   */
  protected static $modules = ['system', 'user', 'ai'];

  /**
   * Tests if the module installs successfully.
   */
  public function testModuleCanBeEnabled() {

    try {
      // Try to enable the module.
      \Drupal::service('module_installer')->install(['ai_translate']);
      $this->assertTrue(\Drupal::service('module_handler')->moduleExists('ai_translate'), 'The module is successfully installed.');
    }
    catch (\Exception $e) {
      $this->fail('The module could not be enabled: ' . $e->getMessage());
    }
  }

  /**
   * Tests that the chat proxy is configured when a chat provider is available.
   */
  public function testChatProxyIsConfiguredOnInstall(): void {
    $this->config('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'test_chat_provider',
        'model_id' => 'test_chat_model',
      ])
      ->save();

    $this->container->get('module_installer')->install(['ai_translate']);

    $this->assertSame([
      'provider_id' => 'chat_translation',
      'model_id' => 'test_chat_model',
    ], $this->config('ai.settings')->get('default_providers.translate_text'));
  }

  /**
   * Tests that installation reports a missing default provider.
   */
  public function testMissingProviderMessageOnInstall(): void {
    $this->container->get('module_installer')->install(['ai_translate']);

    $this->assertNull($this->config('ai.settings')->get('default_providers.translate_text'));
    $messages = $this->container->get('messenger')->messagesByType('error');
    $this->assertCount(1, $messages);
    $this->assertStringContainsString('Please set a default provider & model for text translation', (string) reset($messages));
  }

  /**
   * Tests that an existing translation provider is preserved on install.
   */
  public function testExistingTranslationProviderIsPreserved(): void {
    $this->config('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'test_chat_provider',
        'model_id' => 'test_chat_model',
      ])
      ->set('default_providers.translate_text', [
        'provider_id' => 'test_translate_provider',
        'model_id' => 'test_translate_model',
      ])
      ->save();

    $this->container->get('module_installer')->install(['ai_translate']);

    $this->assertSame([
      'provider_id' => 'test_translate_provider',
      'model_id' => 'test_translate_model',
    ], $this->config('ai.settings')->get('default_providers.translate_text'));
  }

  /**
   * Tests installing the module as part of a config import.
   */
  public function testInstallDuringConfigImport(): void {
    // A config import needs system.site, uninstalling needs users_data.
    $this->installConfig(['system']);
    $this->installSchema('user', ['users_data']);

    // Build a sync directory with ai_translate enabled and both default
    // providers set.
    $this->config('ai.settings')
      ->set('default_providers.chat', [
        'provider_id' => 'test_chat_provider',
        'model_id' => 'test_chat_model',
      ])
      ->set('default_providers.translate_text', [
        'provider_id' => 'test_translate_provider',
        'model_id' => 'test_translate_model',
      ])
      ->save();
    $this->container->get('module_installer')->install(['ai_translate']);
    $sync = $this->container->get('config.storage.sync');
    $this->copyConfig($this->container->get('config.storage'), $sync);

    // Reset the active site to ai_translate disabled and no default providers.
    $this->container->get('module_installer')->uninstall(['ai_translate']);
    $this->config('ai.settings')
      ->clear('default_providers.chat')
      ->clear('default_providers.translate_text')
      ->save();
    $this->container->get('messenger')->deleteAll();

    // Modules are installed before the configuration is imported, so the
    // install hook should not act on the still empty ai.settings.
    $this->configImporter()->import();

    $this->assertTrue($this->container->get('module_handler')->moduleExists('ai_translate'));
    $this->assertSame($sync->read('ai.settings'), $this->container->get('config.storage')->read('ai.settings'));
    $this->assertEmpty($this->container->get('messenger')->messagesByType('error'));
  }

}
