<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_translate\Hook\RequirementsHooks;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the runtime requirements reported by ai_translate.
 *
 * @group ai_translate
 */
#[CoversClass(RequirementsHooks::class)]
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class RequirementsHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'ai'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (version_compare(\Drupal::VERSION, '11.2', '<')) {
      $this->markTestSkipped('hook_runtime_requirements() is only available on Drupal 11.2 and later.');
    }
    $this->container->get('module_installer')->install(['ai_translate']);
  }

  /**
   * Tests that missing default providers are reported once.
   */
  public function testMissingProvidersAreReported(): void {
    $module_handler = $this->container->get('module_handler');

    $requirements = $module_handler->invoke('ai_translate', 'runtime_requirements') ?? [];
    $this->assertArrayHasKey('ai_translate_translate', $requirements);
    $this->assertArrayHasKey('ai_translate_chat', $requirements);

    // The procedural hook_requirements() must not be registered, otherwise the
    // status report would list them a second time.
    $this->assertFalse($module_handler->hasImplementations('requirements', 'ai_translate'));
  }

  /**
   * Tests that nothing is reported when a translate provider is set.
   */
  public function testConfiguredProviderIsNotReported(): void {
    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'test_translate_provider',
        'model_id' => 'test_translate_model',
      ])
      ->save();

    $requirements = $this->container->get('module_handler')->invoke('ai_translate', 'runtime_requirements') ?? [];
    $this->assertArrayNotHasKey('ai_translate_translate', $requirements);
    $this->assertArrayNotHasKey('ai_translate_chat', $requirements);
  }

}
