<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the AI core config entities can be duplicated.
 *
 * @group ai
 */
final class ConfigEntityDuplicateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'key',
    'system',
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
      'ai_guardrail' => ['ai_guardrail'],
      'ai_guardrail_set' => ['ai_guardrail_set'],
      'ai_prompt' => ['ai_prompt'],
      'ai_prompt_type' => ['ai_prompt_type'],
    ];
  }

}
