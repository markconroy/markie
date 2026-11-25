<?php

declare(strict_types=1);

namespace Drupal\Tests\ctools\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the typed data relationship deriver.
 *
 * @coversDefaultClass \Drupal\ctools\Plugin\Deriver\TypedDataRelationshipDeriver
 * @group CTools
 */
#[Group('CTools')]
#[RunTestsInSeparateProcesses]
final class TypedDataRelationshipDeriverTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ctools',
    'entity_test',
    'field',
    'filter',
    'system',
    'text',
    'user',
  ];

  /**
   * Tests fields whose type has no main property.
   *
   * The map field type returns NULL from mainPropertyName(), so no typed data
   * relationship can be derived for it. Derivation must skip it without
   * asking the storage definition for a NULL property name, which core
   * deprecates in drupal:11.3.0 and turns into an exception in drupal:12.0.0.
   *
   * @covers ::generateDerivativeDefinition
   */
  public function testFieldWithoutMainProperty(): void {
    $deprecations = [];
    set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
      if (str_contains($errstr, '::getPropertyDefinition() with a non-string $name')) {
        $deprecations[] = $errstr;
        return TRUE;
      }
      return FALSE;
    }, E_USER_DEPRECATED);
    try {
      $relationship_manager = $this->container->get('plugin.manager.ctools.relationship');
      $relationship_manager->clearCachedDefinitions();
      $definitions = $relationship_manager->getDefinitions();
    }
    finally {
      restore_error_handler();
    }

    $this->assertSame([], $deprecations);
    $this->assertArrayNotHasKey('typed_data_relationship:entity:entity_test_map_field:data', $definitions);
    // Other properties on the same entity type are still derived.
    $this->assertArrayHasKey('typed_data_relationship:entity:entity_test_map_field:name', $definitions);
  }

}
