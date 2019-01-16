<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Value object representing a JSON:API relationship item.
 *
 * @internal
 */
class RelationshipItem {

  /**
   * The target key name.
   *
   * @var string
   */
  protected $targetKey = 'target_id';

  /**
   * The target entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected $targetEntity;

  /**
   * The target JSON:API resource type.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceType
   */
  protected $targetResourceType;

  /**
   * The parent relationship.
   *
   * @var \Drupal\jsonapi\Normalizer\Relationship
   */
  protected $parent;

  /**
   * The list of metadata associated with this relationship item value.
   *
   * @var array
   */
  protected $metadata;

  /**
   * Relationship item constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Entity\EntityInterface|null|false $target_entity
   *   The entity this relationship points to, if any. NULL if virtual resource.
   *   FALSE if missing resource (dangling entity reference).
   * @param \Drupal\jsonapi\Normalizer\Relationship $parent
   *   The parent of this item.
   * @param string $target_key
   *   The key name of the target relationship.
   * @param array $metadata
   *   The list of metadata associated with this relationship item value.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, $target_entity, Relationship $parent, $target_key = 'target_id', array $metadata = []) {
    assert($target_entity === NULL || $target_entity === FALSE || $target_entity instanceof EntityInterface);
    if ($target_entity === NULL || $target_entity === FALSE) {
      $host_entity = $parent->getHostEntity();
      $relatable_resource_types = $resource_type_repository->get(
        $host_entity->getEntityTypeId(),
        $host_entity->bundle()
      )->getRelatableResourceTypes()[$parent->getPropertyName()];

      if ($target_entity === NULL) {
        if (count($relatable_resource_types) !== 1) {
          throw new \RuntimeException('Relationships to virtual resources are possible only if a single resource type is relatable.');
        }
        $this->targetResourceType = reset($relatable_resource_types);
      }
      else {
        assert($target_entity === FALSE);
        // In case of a dangling reference, it is impossible to determine which
        // resource type it used to reference, because that requires knowing the
        // referenced bundle, which Drupal does not store.
        // If we can reliably determine the resource type of the dangling
        // reference, use it; otherwise conjure a fake resource type out of thin
        // air, one that indicates we don't know the bundle.
        $this->targetResourceType = count($relatable_resource_types) > 1
          ? new ResourceType('?', '?', '')
          : reset($relatable_resource_types);
      }
    }
    else {
      $this->targetResourceType = $resource_type_repository->get(
        $target_entity->getEntityTypeId(),
        $target_entity->bundle()
      );
    }
    $this->targetKey = $target_key;
    $this->targetEntity = $target_entity;
    $this->parent = $parent;
    $this->metadata = $metadata;
  }

  /**
   * Gets the target entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The target entity of this relationship item.
   */
  public function getTargetEntity() {
    return $this->targetEntity;
  }

  /**
   * Gets the targetResourceConfig.
   *
   * @return mixed
   *   The target of this relationship item.
   */
  public function getTargetResourceType() {
    return $this->targetResourceType;
  }

  /**
   * Gets the relationship value.
   *
   * Defaults to the entity ID.
   *
   * @return string
   *   The value of this relationship item.
   */
  public function getValue() {
    $target_uuid = $this->targetEntity === NULL
      ? 'virtual'
      : ($this->targetEntity === FALSE
        ? 'missing'
        : $this->getTargetEntity()->uuid());

    return [
      'target_uuid' => $target_uuid,
      'meta' => $this->metadata,
    ];
  }

  /**
   * Gets the relationship object that contains this relationship item.
   *
   * @return \Drupal\jsonapi\Normalizer\Relationship
   *   The parent relationship of this item.
   */
  public function getParent() {
    return $this->parent;
  }

}
