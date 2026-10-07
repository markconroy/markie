<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel\Plugin;

use Drupal\ai_automators\Plugin\AiCKEditor\AiAutomatorsCKEditor;
use Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the AI Automators CKEditor plugin provided to the ai_ckeditor module.
 *
 * The plugin class and its config schema both depend on the ai_ckeditor
 * submodule, so this guards the cross-module integration.
 *
 * @group ai_automators
 */
class AiAutomatorsCKEditorPluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'file',
    'field',
    'filter',
    'editor',
    'ckeditor5',
    'token',
    'ai',
    'ai_ckeditor',
    'ai_automators',
  ];

  /**
   * Tests that the plugin is discovered and can be instantiated.
   */
  public function testPluginIsDiscoveredAndInstantiable(): void {
    /** @var \Drupal\ai_ckeditor\PluginManager\AiCKEditorPluginManager $manager */
    $manager = $this->container->get('plugin.manager.ai_ckeditor');

    $this->assertTrue($manager->hasDefinition('ai_automators_ckeditor'));
    $definition = $manager->getDefinition('ai_automators_ckeditor');
    $this->assertSame(AiAutomatorsCKEditor::class, $definition['class']);
    $this->assertSame('ai_automators', $definition['provider']);

    $plugin = $manager->createInstance('ai_automators_ckeditor');
    $this->assertInstanceOf(AiCKEditorPluginInterface::class, $plugin);
    $this->assertInstanceOf(AiAutomatorsCKEditor::class, $plugin);
    $this->assertArrayHasKey('workflows', $plugin->defaultConfiguration());
  }

  /**
   * Tests that the plugin config schema resolves its ai_ckeditor base type.
   */
  public function testPluginConfigSchemaExists(): void {
    /** @var \Drupal\Core\Config\TypedConfigManagerInterface $typed_config */
    $typed_config = $this->container->get('config.typed');

    $this->assertTrue($typed_config->hasConfigSchema('ckeditor5.plugin.ai_ckeditor_ai_base'));
    $this->assertTrue($typed_config->hasConfigSchema('ckeditor5.plugin.ai_ckeditor_ai.ai_automators_ckeditor'));

    $definition = $typed_config->getDefinition('ckeditor5.plugin.ai_ckeditor_ai.ai_automators_ckeditor');
    $this->assertArrayHasKey('enabled', $definition['mapping']);
    $this->assertArrayHasKey('workflows', $definition['mapping']);
  }

}
