<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_translate\FieldTextExtractorInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that every field text extractor plugin can be instantiated.
 *
 * Plugin create() methods must not rely on core APIs that only exist in some
 * of the supported Drupal versions, such as PluginBase::create() (11.3+).
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class FieldTextExtractorPluginsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'language',
    'content_translation',
    // The layout_builder extractor needs Layout Builder services.
    'block',
    'layout_discovery',
    'layout_builder',
    'ai',
    'ai_translate',
  ];

  /**
   * Tests that all extractor plugins can be created.
   */
  public function testExtractorPluginsCanBeCreated(): void {
    $this->installConfig(['language', 'ai_translate']);

    /** @var \Drupal\ai_translate\FieldTextExtractorPluginManagerInterface $manager */
    $manager = $this->container->get('plugin.manager.text_extractor');
    $definitions = $manager->getDefinitions();
    $this->assertNotEmpty($definitions);

    foreach (array_keys($definitions) as $pluginId) {
      $this->assertInstanceOf(
        FieldTextExtractorInterface::class,
        $manager->createInstance($pluginId),
        "The $pluginId extractor plugin can be created.",
      );
    }
  }

}
