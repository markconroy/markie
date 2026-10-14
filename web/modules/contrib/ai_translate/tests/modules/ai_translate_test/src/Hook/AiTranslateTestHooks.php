<?php

declare(strict_types=1);

namespace Drupal\ai_translate_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ai_translate_test.
 */
class AiTranslateTestHooks {

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    $fields = [];

    if ($entity_type->id() === 'entity_test_mul') {
      $fields['base_ai_translate_test'] = BaseFieldDefinition::create('ai_translate_test')
        ->setLabel('Test base field')
        ->setTranslatable(TRUE)
        ->setDefaultValue('Base test value');
    }

    return $fields;
  }

  /**
   * Implements hook_entity_base_field_info_alter().
   */
  #[Hook('entity_base_field_info_alter')]
  public function entityBaseFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type): void {
    if ($entity_type->id() === 'comment') {
      $fields['field_name']->setTranslatable(TRUE);
    }
  }

}
