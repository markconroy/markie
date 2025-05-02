<?php

namespace Drupal\Tests\entity_usage\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;

/**
 * Tests the deprecation message in entity usage track plugins.
 *
 * @group entity_usage
 * @group legacy
 */
class EntityUsageDeprecationTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_usage_deprecation_test', 'entity_usage'];

  /**
   * Ensures plugins missing source_entity_class trigger a deprecation.
   */
  public function testSourceEntityClassDeprecation(): void {
    $this->expectDeprecation("The plugin definition 'Drupal\\entity_usage_deprecation_test\\Plugin\\EntityUsage\\Track\\DeprecatedSourceEntityClassPlugin' not defining the 'source_entity_class' property is deprecated in entity_usage:8.x-2.0-beta20 and will cause an exception in entity_usage:8.x-2.1. See https://www.drupal.org/node/3505220");
    $definitions = $this->container->get('plugin.manager.entity_usage.track')->getDefinitions();
    $this->assertSame(EntityInterface::class, $definitions['entity_usage_test_deprecation']['source_entity_class']);
  }

}
