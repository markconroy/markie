<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;

/**
 * Represents a JSON:API resource identifier object.
 *
 * @internal
 */
class ResourceIdentifier implements ResourceIdentifierInterface {

  const ARITY_KEY = 'arity';

  /**
   * The JSON:API resource type name.
   *
   * @var string
   */
  protected $resourceTypeName;

  /**
   * The resource ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The relationship's metadata.
   *
   * @var array
   */
  protected $meta;

  /**
   * ResourceIdentifier constructor.
   *
   * @param string $resource_type_name
   *   The JSON:API resource type name.
   * @param string $id
   *   The resource ID.
   * @param array $meta
   *   Any metadata for the ResourceIdentifier.
   */
  public function __construct($resource_type_name, $id, array $meta = []) {
    assert(!isset($meta[static::ARITY_KEY]) || is_int($meta[static::ARITY_KEY]) && $meta[static::ARITY_KEY] >= 0);
    $this->resourceTypeName = $resource_type_name;
    $this->id = $id;
    $this->meta = $meta;
  }

  /**
   * Gets the ResourceIdentifier's JSON:API resource type name.
   *
   * @return string
   *   The JSON:API resource type name.
   */
  public function getTypeName() {
    return $this->resourceTypeName;
  }

  /**
   * Gets the ResourceIdentifier's ID.
   *
   * @return string
   *   The ID.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Whether this ResourceIdentifier has an arity.
   *
   * @return int
   *   TRUE if the ResourceIdentifier has an arity, FALSE otherwise.
   */
  public function hasArity() {
    return isset($this->meta[static::ARITY_KEY]);
  }

  /**
   * Gets the ResourceIdentifier's arity.
   *
   * One must check self::hasArity() before calling this method.
   *
   * @return int
   *   The arity.
   */
  public function getArity() {
    assert($this->hasArity());
    return $this->meta[static::ARITY_KEY];
  }

  /**
   * Returns a copy of the given ResourceIdentifier with the given arity.
   *
   * @param int $arity
   *   The new arity; must be a non-negative integer.
   *
   * @return static
   *   A newly created ResourceIdentifier with the given arity, otherwise
   *   the same.
   */
  public function withArity($arity) {
    return new static($this->getTypeName(), $this->getId(), [static::ARITY_KEY => $arity] + $this->getMeta());
  }

  /**
   * Gets the resource identifier objects metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMeta() {
    return $this->meta;
  }

  /**
   * Determines if two ResourceIdentifiers are the same.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier $a
   *   The first ResourceIdentifier object.
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier $b
   *   The second ResourceIdentifier object.
   *
   * @return bool
   *   TRUE if both relationships reference the same resource and do not have
   *   two distinct arity's, FALSE otherwise.
   *
   *   For example, if $a and $b both reference the same resource identifier,
   *   they can only be distinct if they *both* have an arity and those values
   *   are not the same. If $a or $b does not have an arity, they will be
   *   considered duplicates.
   */
  public static function isDuplicate(ResourceIdentifier $a, ResourceIdentifier $b) {
    return static::compare($a, $b) === 0;
  }

  /**
   * Compares ResourceIdentifier objects.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier $a
   *   The first ResourceIdentifier object.
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier $b
   *   The second ResourceIdentifier object.
   *
   * @return int
   *   Returns 0 if $a and $b are duplicate ResourceIdentifiers. If $a and $b
   *   identify the same resource but have distinct arity values, then the
   *   return value will be arity $a minus arity $b. -1 otherwise.
   */
  public static function compare(ResourceIdentifier $a, ResourceIdentifier $b) {
    $result = strcmp(sprintf('%s:%s', $a->getTypeName(), $a->getId()), sprintf('%s:%s', $b->getTypeName(), $b->getId()));
    // If type and ID do not match, return their ordering.
    if ($result !== 0) {
      return $result;
    }
    // If both $a and $b have an arity, then return the order by arity.
    // Otherwise, they are considered equal.
    return $a->hasArity() && $b->hasArity()
      ? $a->getArity() - $b->getArity()
      : 0;
  }

  /**
   * Deduplicates an array of ResourceIdentifier objects.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers
   *   The list of ResourceIdentifiers to deduplicate.
   *
   * @return \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[]
   *   A deduplicated array of ResourceIdentifier objects.
   *
   * @see self::isDuplicate()
   */
  public static function deduplicate(array $resource_identifiers) {
    return array_reduce(array_slice($resource_identifiers, 1), function ($deduplicated, $current) {
      assert($current instanceof static);
      return array_merge($deduplicated, array_reduce($deduplicated, function ($duplicate, $previous) use ($current) {
        return $duplicate ?: static::isDuplicate($previous, $current);
      }, FALSE) ? [] : [$current]);
    }, array_slice($resource_identifiers, 0, 1));
  }

  /**
   * Determines if an array of ResourceIdentifier objects is duplicate free.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifier[] $resource_identifiers
   *   The list of ResourceIdentifiers to assess.
   *
   * @return bool
   *   Whether all the given resource identifiers are unique.
   */
  public static function areResourceIdentifiersUnique(array $resource_identifiers) {
    return count($resource_identifiers) === count(static::deduplicate($resource_identifiers));
  }

  /**
   * Creates a ResourceIdentifier object.
   *
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item
   *   The entity reference field item from which to create the relationship.
   * @param int $arity
   *   (optional) The arity of the relationship.
   *
   * @return self
   *   A new ResourceIdentifier object.
   */
  public static function toResourceIdentifier(EntityReferenceItem $item, $arity = NULL) {
    $property_name = static::getDataReferencePropertyName($item);
    $target = $item->get($property_name)->getValue();
    assert($target instanceof EntityInterface);
    /* @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository */
    $resource_type_repository = \Drupal::service('jsonapi.resource_type.repository');
    $resource_type = $resource_type_repository->get($target->getEntityTypeId(), $target->bundle());
    // Remove unwanted properties from the meta value, usually 'entity'
    // and 'target_id'.
    $meta = array_diff_key($item->getValue(), array_flip([$property_name, $item->getDataDefinition()->getMainPropertyName()]));
    if (!is_null($arity)) {
      $meta[static::ARITY_KEY] = $arity;
    }
    return new static($resource_type->getTypeName(), $target->uuid(), $meta);
  }

  /**
   * Creates an array of ResourceIdentifier objects.
   *
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items
   *   The entity reference field items from which to create the relationship
   *   array.
   *
   * @return self[]
   *   An array of new ResourceIdentifier objects with appropriate arity values.
   */
  public static function toResourceIdentifiers(EntityReferenceFieldItemListInterface $items) {
    $relationships = [];
    foreach ($items as $item) {
      $relationship = static::toResourceIdentifier($item, 0);
      /* @var self $existing */
      foreach (array_reverse($relationships) as $index => $existing) {
        $is_duplicate = static::isDuplicate($existing, $relationship);
        if ($is_duplicate) {
          $relationships[] = $relationship->withArity($relationships[$index]->getArity() + 1);
          continue 2;
        }
      }
      $relationships[] = $relationship;
    }
    return $relationships;
  }

  /**
   * Creates a ResourceIdentifier object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which to create the resource identifier.
   *
   * @return self
   *   A new ResourceIdentifier object.
   */
  public static function fromEntity(EntityInterface $entity) {
    /* @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository */
    $resource_type_repository = \Drupal::service('jsonapi.resource_type.repository');
    $resource_type = $resource_type_repository->get($entity->getEntityTypeId(), $entity->bundle());
    return new static($resource_type->getTypeName(), $entity->uuid());
  }

  /**
   * Helper method to determine which field item property contains an entity.
   *
   * @param \Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem $item
   *   The entity reference item for which to determine the entity property
   *   name.
   *
   * @return string
   *   The property name which has an entity as its value.
   */
  protected static function getDataReferencePropertyName(EntityReferenceItem $item) {
    foreach ($item->getDataDefinition()->getPropertyDefinitions() as $property_name => $property_definition) {
      if ($property_definition instanceof DataReferenceDefinitionInterface) {
        return $property_name;
      }
    }
  }

}
