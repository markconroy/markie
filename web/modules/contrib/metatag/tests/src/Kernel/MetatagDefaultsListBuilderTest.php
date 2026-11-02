<?php

namespace Drupal\Tests\metatag\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\metatag\MetatagDefaultsListBuilder;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Metatag defaults list builder.
 *
 * @group metatag
 */
#[RunTestsInSeparateProcesses]
class MetatagDefaultsListBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Core modules.
    'system',
    'user',

    // Contrib modules.
    'token',

    // This module.
    'metatag',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['system', 'metatag']);
  }

  /**
   * Tests render() when maintenance mode is disabled and enabled.
   */
  public function testRenderMaintenanceMode(): void {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $list_builder = $entity_type_manager->getListBuilder('metatag_defaults');
    $this->assertInstanceOf(MetatagDefaultsListBuilder::class, $list_builder);

    /** @var \Drupal\Core\State\StateInterface $state */
    $state = $this->container->get('state');
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');

    // 1. When maintenance mode is off, no message is added and defaults list
    // renders.
    $state->set('system.maintenance_mode', FALSE);
    $build = $list_builder->render();
    $this->assertIsArray($build);
    $this->assertArrayHasKey('table', $build);
    $this->assertEmpty($messenger->all());

    // 2. When maintenance mode is on, message is added and defaults list
    // renders without error.
    $state->set('system.maintenance_mode', TRUE);
    $build = $list_builder->render();
    $this->assertIsArray($build);
    $this->assertArrayHasKey('table', $build);

    $messages = $messenger->all();
    $this->assertNotEmpty($messages['status']);
    $expected_message = 'Please note that while the site is in maintenance mode none of the usual meta tags will be output.';
    $this->assertEquals($expected_message, (string) $messages['status'][0]);
  }

  /**
   * Tests backward-compatible constructor without messenger parameter.
   */
  public function testConstructorBackwardCompatibility(): void {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition('metatag_defaults');
    $storage = $entity_type_manager->getStorage('metatag_defaults');
    /** @var \Drupal\Core\State\StateInterface $state */
    $state = $this->container->get('state');
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = $this->container->get('messenger');

    $messenger->deleteAll();
    $state->set('system.maintenance_mode', TRUE);

    // Instantiate with only 3 arguments (legacy signature).
    $list_builder = new MetatagDefaultsListBuilder($entity_type, $storage, $state);
    $build = $list_builder->render();
    $this->assertIsArray($build);
    $this->assertArrayHasKey('table', $build);

    $messages = $messenger->all();
    $this->assertNotEmpty($messages['status']);
    $expected_message = 'Please note that while the site is in maintenance mode none of the usual meta tags will be output.';
    $this->assertEquals($expected_message, (string) $messages['status'][0]);
  }

}
