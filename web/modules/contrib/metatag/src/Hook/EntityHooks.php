<?php

namespace Drupal\metatag\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Entity hook implementations for Metatag.
 */
class EntityHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    $fields = [];
    $base_table = $entity_type->getBaseTable();
    $canonical_template_exists = $entity_type->hasLinkTemplate('canonical');
    // Certain classes are just not supported.
    $original_class = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.4.0', fn() => $entity_type->getDecoratedClasses()[0] ?? $entity_type->getClass(), fn() => $entity_type->getOriginalClass());
    $classes_to_skip = [
      'Drupal\comment\Entity\Comment',
    ];

    // If the entity type doesn't have a base table, has no link template then
    // there's no point in supporting it.
    if (!empty($base_table) && $canonical_template_exists && !in_array($original_class, $classes_to_skip)) {
      $fields['metatag'] = BaseFieldDefinition::create('metatag_computed')->setLabel($this->t('Metatags (Hidden field for JSON support)'))->setDescription($this->t('The computed meta tags for the entity.'))->setComputed(TRUE)->setTranslatable(TRUE)->setReadOnly(TRUE)->setTargetEntityTypeId($entity_type->id())->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    }
    return $fields;
  }

}
