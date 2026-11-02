<?php

namespace Drupal\Tests\metatag\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that tag and group plugins expose their descriptions.
 *
 * @group metatag
 */
class MetatagPluginDescriptionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Contrib modules.
    'token',

    // This module.
    'metatag',
  ];

  /**
   * Tests that every tag plugin returns the description from its definition.
   */
  public function testTagDescriptions(): void {
    $this->assertPluginDescriptions('plugin.manager.metatag.tag');
  }

  /**
   * Tests that every group plugin returns the description from its definition.
   */
  public function testGroupDescriptions(): void {
    $this->assertPluginDescriptions('plugin.manager.metatag.group');
  }

  /**
   * Asserts that each plugin's description matches its definition.
   *
   * @param string $manager_id
   *   The plugin manager service ID.
   */
  protected function assertPluginDescriptions(string $manager_id): void {
    $manager = $this->container->get($manager_id);
    $definitions = $manager->getDefinitions();
    $this->assertNotEmpty($definitions);
    foreach ($definitions as $plugin_id => $definition) {
      $this->assertNotEmpty($definition['description'], "The $plugin_id plugin definition has a description.");
      $plugin = $manager->createInstance($plugin_id);
      $this->assertEquals((string) $definition['description'], (string) $plugin->description(), "The $plugin_id plugin description matches its definition.");
    }
  }

}
