<?php

declare(strict_types=1);

namespace Drupal\ai_translate_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;

/**
 * Hook implementations for ai_translate_test.
 */
class AiTranslateTestHooks {

  public function __construct(
    protected StateInterface $state,
  ) {
  }

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

  /**
   * Implements hook_ai_translate_translation_alter().
   *
   * Records what the chat proxy resolved, so a test can assert the hook is
   * handed the provider and model that will really run.
   */
  #[Hook('ai_translate_translation_alter')]
  public function aiTranslateTranslationAlter(&$messages, &$provider_id, &$model_id): void {
    $this->state->set('ai_translate_test.alter_args', [$provider_id, $model_id]);
  }

}
