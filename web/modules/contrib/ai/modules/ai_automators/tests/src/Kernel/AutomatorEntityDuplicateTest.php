<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the AI automator config entities can be duplicated.
 *
 * @group ai_automators
 */
final class AutomatorEntityDuplicateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * Tests that createDuplicate() returns a copy without an ID.
   *
   * @param string $entity_type_id
   *   The config entity type ID.
   *
   * @dataProvider entityTypeProvider
   */
  public function testCreateDuplicate(string $entity_type_id): void {
    $entity = $this->container->get('entity_type.manager')
      ->getStorage($entity_type_id)
      ->create(['id' => 'original', 'label' => 'Original']);

    $duplicate = $entity->createDuplicate();

    $this->assertNull($duplicate->id());
    $this->assertNotSame($entity->uuid(), $duplicate->uuid());
    $this->assertSame('original', $entity->id());
  }

  /**
   * Provides the config entity type IDs to test.
   *
   * @return array<string, array{string}>
   *   The test cases, keyed by entity type ID.
   */
  public static function entityTypeProvider(): array {
    return [
      'ai_automator' => ['ai_automator'],
      'automators_tool' => ['automators_tool'],
      'automator_chain_type' => ['automator_chain_type'],
    ];
  }

}
