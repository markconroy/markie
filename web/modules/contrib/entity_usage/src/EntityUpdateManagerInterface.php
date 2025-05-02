<?php

namespace Drupal\entity_usage;

use Drupal\Core\Entity\EntityInterface;

/**
 * The interface implemented by the entity update manager.
 *
 * @package Drupal\entity_usage
 */
interface EntityUpdateManagerInterface {

  /**
   * Tracks updates on creation of potential source entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are dealing with.
   */
  public function trackUpdateOnCreation(EntityInterface $entity): void;

  /**
   * Tracks updates on edits/updates of potential source entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are dealing with.
   */
  public function trackUpdateOnEdition(EntityInterface $entity): void;

  /**
   * Tracks updates on deletion of entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity we are dealing with.
   * @param 'revision'|'translation'|'default' $type
   *   What type of deletion is being performed:
   *   - default: The main entity (default language, default revision) is being
   *   deleted (delete also other languages and revisions).
   *   - translation: Only one translation is being deleted.
   *   - revision: Only one revision is being deleted.
   *
   * @throws \InvalidArgumentException
   */
  public function trackUpdateOnDeletion(EntityInterface $entity, $type = 'default'): void;

}
