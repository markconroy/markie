<?php

declare(strict_types=1);

namespace Drupal\ctools_entity_mask\Hook;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\ctools_entity_mask\MaskContentEntityStorage;

/**
 * Hook implementations for ctools_entity_mask.
 */
final class CtoolsEntityMaskHooks {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Ensures that mask entity types have the same display modes as masked ones.
   *
   * @param array $display_modes
   *   The display modes.
   *
   * @see hook_entity_view_mode_info_alter()
   * @see \Drupal\Core\Entity\EntityDisplayRepository::getAllDisplayModesByEntityType()
   */
  public function copyDisplayModes(array &$display_modes): void {
    foreach ($this->entityTypeManager->getDefinitions() as $id => $entity_type) {
      $mask = $entity_type->get('mask');

      if ($mask && isset($display_modes[$mask])) {
        $display_modes[$id] = $display_modes[$mask];
      }
    }
  }

  /**
   * Implements hook_entity_view_mode_info_alter().
   */
  #[Hook('entity_view_mode_info_alter')]
  public function entityViewModeInfoAlter(array &$view_modes): void {
    $this->copyDisplayModes($view_modes);
  }

  /**
   * Implements hook_entity_form_mode_info_alter().
   */
  #[Hook('entity_form_mode_info_alter')]
  public function entityFormModeInfoAlter(array &$form_modes): void {
    $this->copyDisplayModes($form_modes);
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type */
    foreach ($entity_types as $entity_type) {
      // Mask entities should use our specialized storage handler, which
      // simulates a save but does not write anything to the database.
      if ($entity_type->get('mask') && $entity_type->getStorageClass() == SqlContentEntityStorage::class) {
        $entity_type->setStorageClass(MaskContentEntityStorage::class);
        // Mask entities should not maintain any tables.
        $entity_type->set('base_table', NULL);
        $entity_type->set('revision_table', NULL);
        $entity_type->set('data_table', NULL);
        $entity_type->set('revision_data_table', NULL);

        // Nor should they be exposed to Field UI.
        $entity_type->set('field_ui_base_route', NULL);
      }
    }
  }

  /**
   * Copies all components from a display for a masked entity type.
   *
   * If the given display is for a mask entity type, the corresponding display
   * for the masked entity type is loaded and all of its components are copied
   * into the given display. If no corresponding display exists for the masked
   * entity type, the default display will be loaded and used.
   *
   * @param \Drupal\Core\Entity\Display\EntityDisplayInterface $display
   *   The display for the mask entity type.
   */
  public function copyDisplay(EntityDisplayInterface $display): void {
    $mask = $this->entityTypeManager
      ->getDefinition($display->getTargetEntityTypeId())
      ->get('mask');

    // If the target entity type is not masking another entity type, there is
    // nothing to do here.
    if (empty($mask)) {
      return;
    }

    // Try to load the corresponding entity display for the masked entity type,
    // in descending order of preference.
    $bundle = $display->getTargetBundle();
    $displays = $display::loadMultiple([
      $mask . '.' . $bundle . '.' . $display->getMode(),
      $mask . '.' . $bundle . '.default',
    ]);

    // Nothing to do if there is no display we can borrow components from.
    if (empty($displays)) {
      return;
    }
    foreach (reset($displays)->getComponents() as $key => $component) {
      $display->setComponent($key, $component);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for entity_view_display.
   */
  #[Hook('entity_view_display_create')]
  public function entityViewDisplayCreate(EntityViewDisplayInterface $display): void {
    $this->copyDisplay($display);
  }

  /**
   * Implements hook_ENTITY_TYPE_create() for entity_form_display.
   */
  #[Hook('entity_form_display_create')]
  public function entityFormDisplayCreate(EntityFormDisplayInterface $display): void {
    $this->copyDisplay($display);
  }

  /**
   * Implements hook_entity_view_display_alter().
   */
  #[Hook('entity_view_display_alter')]
  public function entityViewDisplayAlter(EntityViewDisplayInterface $display, array $context): void {
    $this->copyDisplay($display);
  }

  /**
   * Implements hook_entity_form_display_alter().
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    $this->copyDisplay($form_display);
  }

  /**
   * Implements hook_entity_bundle_field_info().
   */
  #[Hook('entity_bundle_field_info')]
  public function entityBundleFieldInfo(EntityTypeInterface $entity_type, $bundle): array {
    $info = [];

    $mask = $entity_type->get('mask');
    // Nothing to do if the entity type is not masking another entity type.
    if (empty($mask)) {
      return $info;
    }

    $storage_info = $this->entityFieldStorageInfo($entity_type);

    /** @var \Drupal\field\FieldConfigInterface[] $fields */
    $fields = $this->entityTypeManager
      ->getStorage('field_config')
      ->loadByProperties([
        'entity_type' => $mask,
        'bundle' => $bundle,
      ]);

    foreach ($fields as $field) {
      $field_name = $field->getName();

      $info[$field_name] = $field
        ->createDuplicate()
        ->set('entity_type', $mask)
        ->set('fieldStorage', $storage_info[$field_name]);
    }
    return $info;
  }

  /**
   * Implements hook_entity_field_storage_info().
   */
  #[Hook('entity_field_storage_info')]
  public function entityFieldStorageInfo(EntityTypeInterface $entity_type): array {
    $info = [];

    $mask = $entity_type->get('mask');
    // Nothing to do if the entity type is not masking another entity type.
    if (empty($mask)) {
      return $info;
    }

    /** @var \Drupal\field\FieldStorageConfigInterface[] $fields */
    $fields = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->loadByProperties([
        'entity_type' => $mask,
      ]);

    foreach ($fields as $field) {
      $field_name = $field->getName();
      $info[$field_name] = $field->createDuplicate()->set('entity_type', $mask);
    }
    return $info;
  }

}
