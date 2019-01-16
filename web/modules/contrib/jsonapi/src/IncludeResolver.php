<?php

namespace Drupal\jsonapi;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\jsonapi\Context\FieldResolver;
use Drupal\jsonapi\Controller\EntityResource;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Resolves included resources for an entity or collection of entities.
 *
 * @internal
 */
class IncludeResolver {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * IncludeResolver constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Resolves included resources.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $base_resource_type
   *   The base resource type for which includes are to be resolved.
   * @param \Drupal\Core\Entity\EntityInterface|\Drupal\jsonapi\JsonApiResource\EntityCollection $data
   *   The resource(s) for which to resolve includes.
   * @param string $include_parameter
   *   The include query parameter to resolve.
   * @param string|null $related_field
   *   A related field if the includes should be resolved for a related route.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   An EntityCollection of resolved resources to be included.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if an included entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if a storage handler couldn't be loaded.
   */
  public function resolve(ResourceType $base_resource_type, $data, $include_parameter, $related_field = NULL) {
    // Map a single entity into an EntityCollection.
    $entity_collection = $data instanceof EntityInterface ? new EntityCollection([$data], 1) : $data;
    $include_tree = static::toIncludeTree($base_resource_type, $include_parameter, $related_field);
    return EntityCollection::deduplicate($this->resolveIncludeTree($include_tree, $entity_collection));
  }

  /**
   * Receives a tree of include field names and resolves resources for it.
   *
   * This method takes a tree of relationship field names and an
   * EntityCollection object. For the top-level of the tree and for each entity
   * in the collection, it gets the target entity type and IDs for each
   * relationship field. The method then loads all of those targets and calls
   * itself recursively with the next level of the tree and those loaded
   * resources.
   *
   * @param array $include_tree
   *   The include paths, represented as a tree.
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $entity_collection
   *   The entity collection from which includes should be resolved.
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection|null $includes
   *   (Internal use only) Any prior resolved includes.
   *
   * @return \Drupal\jsonapi\JsonApiResource\EntityCollection
   *   An EntityCollection of included items.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if an included entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if a storage handler couldn't be loaded.
   */
  protected function resolveIncludeTree(array $include_tree, EntityCollection $entity_collection, EntityCollection $includes = NULL) {
    $includes = is_null($includes) ? new EntityCollection([]) : $includes;
    foreach ($include_tree as $field_name => $children) {
      $references = [];
      foreach ($entity_collection as $entity) {
        // Some entities in the collection may be LabelOnlyEntity objects or
        // EntityAccessDeniedHttpException objects, or they may be entities
        // which do not have fields and cannot have relationships.
        if ($entity instanceof LabelOnlyEntity) {
          $message = "The current user is not allowed to view this relationship.";
          $exception = new EntityAccessDeniedHttpException($entity->getEntity(), AccessResult::forbidden("The user only has authorization for the 'view label' operation."), '', $message, $field_name);
          $includes = EntityCollection::merge($includes, new EntityCollection([$exception]));
          continue;
        }
        elseif (!$entity instanceof FieldableEntityInterface) {
          continue;
        }
        // Not all entities in $entity_collection will be of the same bundle and
        // may not have all of the same fields. Therefore, calling
        // $entity->get($a_missing_field_name) will result in an exception.
        if (!$entity->hasField($field_name)) {
          continue;
        }
        $field_list = $entity->get($field_name);
        // @todo: raise an omitted item to an inaccessible related field in https://www.drupal.org/project/jsonapi/issues/2956084.
        $field_access = $field_list->access('view', NULL, TRUE);
        if (!$field_access->isAllowed()) {
          $message = 'The current user is not allowed to view this relationship.';
          $exception = new EntityAccessDeniedHttpException($entity, $field_access, '', $message, $field_name);
          $includes = EntityCollection::merge($includes, new EntityCollection([$exception]));
          continue;
        }
        $target_type = $field_list->getFieldDefinition()->getFieldStorageDefinition()->getSetting('target_type');
        assert(!empty($target_type));
        foreach ($field_list as $field_item) {
          assert($field_item instanceof EntityReferenceItem);
          $references[$target_type][] = $field_item->get($field_item::mainPropertyName())->getValue();
        }
      }
      foreach ($references as $target_type => $ids) {
        $entity_storage = $this->entityTypeManager->getStorage($target_type);
        $targeted_entities = $entity_storage->loadMultiple(array_unique($ids));
        $access_checked_entities = array_map(function (EntityInterface $entity) {
          return EntityResource::getAccessCheckedEntity($entity);
        }, $targeted_entities);
        $targeted_collection = new EntityCollection($access_checked_entities);
        $includes = static::resolveIncludeTree($children, $targeted_collection, EntityCollection::merge($includes, $targeted_collection));
      }
    }
    return $includes;
  }

  /**
   * Returns a tree of field names to include from an include parameter.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $base_resource_type
   *   The base resource type from which to resolve an internal include path.
   * @param string $include_parameter
   *   The raw include parameter value.
   * @param string|null $related_field
   *   A relationship field name if the includes are being resolved on a
   *   relationship route.
   *
   * @return array
   *   An multi-dimensional array representing a tree of field names to be
   *   included. Array keys are the field names. Leaves are empty arrays.
   */
  protected static function toIncludeTree(ResourceType $base_resource_type, $include_parameter, $related_field) {
    // $include_parameter: 'one.two.three, one.two.four'.
    $include_paths = array_map('trim', explode(',', $include_parameter));
    // $exploded_paths: [['one', 'two', 'three'], ['one', 'two', 'four']].
    $exploded_paths = array_map(function ($include_path) {
      return array_map('trim', explode('.', $include_path));
    }, $include_paths);
    $resolved_paths = static::resolveInternalIncludePaths($base_resource_type, $exploded_paths, $related_field);
    return static::buildTree($resolved_paths);
  }

  /**
   * Resolves an array of public field paths.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $base_resource_type
   *   The base resource type from which to resolve an internal include path.
   * @param array $paths
   *   An array of exploded include paths.
   * @param string|null $related_field
   *   A relationship field name if the includes are being resolved on a
   *   relationship route.
   *
   * @return array
   *   An array of all possible internal include paths derived from the given
   *   public include paths.
   *
   * @see self::buildTree
   */
  protected static function resolveInternalIncludePaths(ResourceType $base_resource_type, array $paths, $related_field) {
    $internal_paths = array_map(function ($exploded_path) use ($base_resource_type, $related_field) {
      if (empty($exploded_path)) {
        return [];
      }
      $resolved_paths = FieldResolver::resolveInternalIncludePath($base_resource_type, $related_field ? array_merge([$related_field], $exploded_path) : $exploded_path);
      return $related_field
        ? array_map(function ($resolved_path) {
          return array_slice($resolved_path, 1);
        }, $resolved_paths)
        : $resolved_paths;
    }, $paths);
    $flattened_paths = array_reduce($internal_paths, 'array_merge', []);
    return $flattened_paths;
  }

  /**
   * Takes an array of exploded paths and builds a tree of field names.
   *
   * Input example: [
   *   ['one', 'two', 'three'],
   *   ['one', 'two', 'four'],
   *   ['one', 'two', 'internal'],
   * ]
   *
   * Output example: [
   *   'one' => [
   *     'two' [
   *       'three' => [],
   *       'four' => [],
   *       'internal' => [],
   *     ],
   *   ],
   * ]
   *
   * @param array $paths
   *   An array of exploded include paths.
   *
   * @return array
   *   An multi-dimensional array representing a tree of field names to be
   *   included. Array keys are the field names. Leaves are empty arrays.
   */
  protected static function buildTree(array $paths) {
    $merged = [];
    foreach ($paths as $parts) {
      if (!$field_name = array_shift($parts)) {
        continue;
      }
      $previous = isset($merged[$field_name]) ? $merged[$field_name] : [];
      $merged[$field_name] = array_merge($previous, [$parts]);
    }
    return !empty($merged) ? array_map([static::class, __FUNCTION__], $merged) : $merged;
  }

}
