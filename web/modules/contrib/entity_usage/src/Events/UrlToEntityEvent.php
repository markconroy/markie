<?php

namespace Drupal\entity_usage\Events;

use Drupal\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;

/**
 * Implementation of URL to entity event.
 */
class UrlToEntityEvent extends Event {

  /**
   * The entity type ID.
   */
  private string $entityTypeId;

  /**
   * The entity ID.
   */
  private string|int $entityId;

  /**
   * The URL's langcode.
   */
  private string $langcode;

  /**
   * Constructs the event object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param string $pathProcessedUrl
   *   The inbound path processed URL.
   * @param string[]|null $enabledTargetEntityTypes
   *   The enabled entity types for tracking.
   */
  public function __construct(private readonly Request $request, public readonly string $pathProcessedUrl, private readonly ?array $enabledTargetEntityTypes) {
  }

  /**
   * Sets the langcode.
   *
   * @param string $langcode
   *   The URLs langcode.
   */
  public function setLangcode(string $langcode): void {
    $this->langcode = $langcode;
  }

  /**
   * Gets the langcode.
   *
   * @return string
   *   The URLs langcode.
   */
  public function getLangcode(): string {
    if (!isset($this->langcode)) {
      throw new \RuntimeException('Langcode not set, no event subscriber should be registered with a priority higher than \Drupal\entity_usage\UrlToEntityIntegrations\LanguageIntegration');
    }
    return $this->langcode;
  }

  /**
   * Sets the entity ID and type ID if the entity type is tracked.
   *
   * Once the entity information is set propagation is stopped as there is no
   * need to invoke any more event listeners.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param string|int $entity_id
   *   The entity ID.
   */
  public function setEntityInfo(string $entity_type_id, string|int $entity_id): void {
    if ($this->isEntityTypeTracked($entity_type_id)) {
      $this->entityTypeId = $entity_type_id;
      $this->entityId = $entity_id;
      $this->stopPropagation();
    }
  }

  /**
   * Gets the entity info, if set.
   *
   * @return string[]|null
   *   An array with two values, the entity type and entity ID, or NULL if no
   *   entity could be retrieved.
   */
  public function getEntityInfo(): ?array {
    if (!isset($this->entityTypeId) || !isset($this->entityId)) {
      return NULL;
    }
    return ['type' => $this->entityTypeId, 'id' => $this->entityId];
  }

  /**
   * Determines if an entity type is tracked.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   Determines if an entity type is tracked.
   */
  public function isEntityTypeTracked(string $entity_type_id): bool {
    // Every entity type is tracked if not set.
    return $this->enabledTargetEntityTypes === NULL || in_array($entity_type_id, $this->enabledTargetEntityTypes, TRUE);
  }

  /**
   * The request representing the URL.
   *
   * @return \Symfony\Component\HttpFoundation\Request
   *   The request object.
   */
  public function getRequest(): Request {
    return $this->request;
  }

}
