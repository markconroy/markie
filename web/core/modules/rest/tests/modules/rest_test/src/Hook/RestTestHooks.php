<?php

declare(strict_types=1);

namespace Drupal\rest_test\Hook;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for rest_test.
 */
class RestTestHooks {

  /**
   * Implements hook_entity_field_access().
   *
   * @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::setUp()
   */
  #[Hook('entity_field_access')]
  public function entityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testPost()
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testPatch()
    if ($field_definition->getName() === 'field_rest_test') {
      switch ($operation) {
        case 'view':
          // Never ever allow this field to be viewed: this lets
          // EntityResourceTestBase::testGet() test in a "vanilla" way.
          return AccessResult::forbidden();

        case 'edit':
          return AccessResult::forbidden();
      }
    }
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testGet()
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testPatch()
    if ($field_definition->getName() === 'field_rest_test_multivalue') {
      switch ($operation) {
        case 'view':
          // Never ever allow this field to be viewed: this lets
          // EntityResourceTestBase::testGet() test in a "vanilla" way.
          return AccessResult::forbidden();
      }
    }
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testGet()
    // @see \Drupal\Tests\rest\Functional\EntityResource\EntityResourceTestBase::testPatch()
    if ($field_definition->getName() === 'rest_test_validation') {
      switch ($operation) {
        case 'view':
          // Never ever allow this field to be viewed: this lets
          // EntityResourceTestBase::testGet() test in a "vanilla" way.
          return AccessResult::forbidden();
      }
    }
    // No opinion.
    return AccessResult::neutral();
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];
    $fields['rest_test_validation'] = BaseFieldDefinition::create('string')->setLabel('REST test validation field')->setDescription('A text field with some special validations attached used for testing purposes')->addConstraint('rest_test_validation');
    return $fields;
  }

}
