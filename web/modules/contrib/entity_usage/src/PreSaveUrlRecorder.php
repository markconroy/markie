<?php

namespace Drupal\entity_usage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;
use Drupal\Core\Entity\Exception\UndefinedLinkTemplateException;

/**
 * Records URLS of entities before they are updated.
 *
 * Some relationships rely on the target entity's URL (such as an HTML link).
 * However, when this URL changes (for example due to a path alias changing),
 * the relationship chain is no longer valid, and we need to refresh usage info.
 * Since we can't use $entity->original to figure out the difference between the
 * original entity's URL and the new one after save, we use this service to
 * temporarily store the URLs before being saved.
 *
 * @see \Drupal\entity_usage\EntityUpdateManager::trackUpdateOnEdition()
 * @see \Drupal\entity_usage\EntityUsageTrackUrlUpdateInterface
 *
 * @internal
 */
class PreSaveUrlRecorder {

  /**
   * A list of entity URLs keyed by the entity type, ID and language.
   *
   * @var string[]
   */
  protected array $urls = [];

  /**
   * Records an entity's URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to record.
   */
  public function recordEntity(EntityInterface $entity): void {
    try {
      $this->urls[$this->key($entity)] = $entity->toUrl()->toString();
    }
    catch (EntityMalformedException | UndefinedLinkTemplateException) {
      // If we cannot create a URL then there is no URL to record.
    }
  }

  /**
   * Gets the recorded URL for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the recorded URL for.
   *
   * @return string|null
   *   The entity's URL or NULL if the entity is not recorded.
   */
  public function getUrl(EntityInterface $entity): ?string {
    return $this->urls[$this->key($entity)] ?? NULL;
  }

  /**
   * Removes an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to remove.
   */
  public function removeEntity(EntityInterface $entity): void {
    unset($this->urls[$this->key($entity)]);
  }

  /**
   * Generates a key for an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to generate a key for.
   *
   * @return string
   *   A unique key for the combination of entity type, entity ID, and entity
   *   language
   */
  private function key(EntityInterface $entity): string {
    return $entity->getEntityTypeId() . '|' . $entity->id() . '|' . $entity->language()->getId();
  }

}
