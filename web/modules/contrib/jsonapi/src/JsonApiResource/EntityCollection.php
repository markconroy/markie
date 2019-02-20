<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Component\Assertion\Inspector;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;

/**
 * Wrapper to normalize collections with multiple entities.
 *
 * @internal
 */
class EntityCollection implements \IteratorAggregate, \Countable {

  /**
   * Various representations of entities.
   *
   * @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface[]
   */
  protected $resourceObjects;

  /**
   * The number of resources permitted in this collection.
   *
   * @var int
   */
  protected $cardinality;

  /**
   * Holds a boolean indicating if there is a next page.
   *
   * @var bool
   */
  protected $hasNextPage;

  /**
   * Holds the total count of entities.
   *
   * @var int
   */
  protected $count;

  /**
   * Instantiates a EntityCollection object.
   *
   * @param \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface[] $resources
   *   The resources for the collection.
   * @param int $cardinality
   *   The number of resources that this collection may contain. Related
   *   resource collections may handle both to-one or to-many relationships. A
   *   to-one relationship should have a cardinality of 1. Use -1 for unlimited
   *   cardinality.
   */
  public function __construct(array $resources, $cardinality = -1) {
    assert(Inspector::assertAllObjects($resources, ResourceIdentifierInterface::class));
    assert($cardinality >= -1 && $cardinality !== 0, 'Cardinality must be -1 for unlimited cardinality or a positive integer.');
    assert($cardinality === -1 || count($resources) <= $cardinality, 'If cardinality is not unlimited, the number of given resources must not exceed the cardinality of the collection.');
    $this->resourceObjects = array_values($resources);
    $this->cardinality = $cardinality;
  }

  /**
   * Returns an iterator for entities.
   *
   * @return \ArrayIterator
   *   An \ArrayIterator instance
   */
  public function getIterator() {
    return new \ArrayIterator($this->resourceObjects);
  }

  /**
   * Returns the number of entities.
   *
   * @return int
   *   The number of parameters
   */
  public function count() {
    return count($this->resourceObjects);
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalCount() {
    return $this->count;
  }

  /**
   * {@inheritdoc}
   */
  public function setTotalCount($count) {
    $this->count = $count;
  }

  /**
   * Returns the collection as an array.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The array of entities.
   */
  public function toArray() {
    return $this->resourceObjects;
  }

  /**
   * Checks if there is a next page in the collection.
   *
   * @return bool
   *   TRUE if the collection has a next page.
   */
  public function hasNextPage() {
    return (bool) $this->hasNextPage;
  }

  /**
   * Sets the has next page flag.
   *
   * Once the collection query has been executed and we build the entity
   * collection, we now if there will be a next page with extra entities.
   *
   * @param bool $has_next_page
   *   TRUE if the collection has a next page.
   */
  public function setHasNextPage($has_next_page) {
    $this->hasNextPage = (bool) $has_next_page;
  }

  /**
   * Gets the cardinality of this collection.
   *
   * @return int
   *   The cardinality of the resource collection. -1 for unlimited cardinality.
   */
  public function getCardinality() {
    return $this->cardinality;
  }

  /**
   * Returns a new EntityCollection containing the entities of $this and $other.
   *
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $a
   *   An EntityCollection object to be merged.
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $b
   *   An EntityCollection object to be merged.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   A new merged EntityCollection object.
   */
  public static function merge(EntityCollection $a, EntityCollection $b) {
    return new static(array_merge($a->toArray(), $b->toArray()));
  }

  /**
   * Returns a new, deduplicated EntityCollection.
   *
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $collection
   *   The EntityCollection to deduplicate.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   A new merged EntityCollection object.
   */
  public static function deduplicate(EntityCollection $collection) {
    $deduplicated = [];
    foreach ($collection as $resource) {
      $dedupe_key = $resource->getTypeName() . ':' . $resource->getId();
      if ($resource instanceof EntityAccessDeniedHttpException && ($error = $resource->getError()) && !is_null($error['relationship_field'])) {
        $dedupe_key .= ':' . $error['relationship_field'];
      }
      $deduplicated[$dedupe_key] = $resource;
    }
    return new static(array_values($deduplicated));
  }

}
