<?php

namespace Drupal\jsonapi\JsonApiResource;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableDependencyTrait;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\Routing\Routes;

/**
 * Represents a JSON:API resource object.
 *
 * This value object wraps a Drupal entity so that it can carry a JSON:API
 * resource type object alongside it. It also helps abstract away differences
 * between config and content entities within the JSON:API codebase.
 *
 * @internal
 */
class ResourceObject implements CacheableDependencyInterface, ResourceIdentifierInterface {

  use CacheableDependencyTrait;
  use ResourceIdentifierTrait;

  /**
   * The object's fields.
   *
   * This refers to "fields" in the JSON:API sense of the word. Config entities
   * do not have real fields, so in that case, this will be an array of values
   * for config entity attributes.
   *
   * @var \Drupal\Core\Field\FieldItemListInterface[]|mixed[]
   */
  protected $fields;

  /**
   * The entity represented by this resource object.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The resource object's links.
   *
   * @var \Drupal\jsonapi\JsonApiResource\LinkCollection
   */
  protected $links;

  /**
   * ResourceObject constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type of the resource object.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be represented by this resource object.
   * @param \Drupal\jsonapi\JsonApiResource\LinkCollection $links
   *   (optional) Any links for the resource object, if a `self` link is not
   *   provided, one will be automatically added if the resource is locatable
   *   and is not an internal entity.
   */
  public function __construct(ResourceType $resource_type, EntityInterface $entity, LinkCollection $links = NULL) {
    $this->setCacheability($entity);
    $this->resourceType = $resource_type;
    $this->entity = $entity;
    $this->fields = $this->extractFields($entity);
    $this->resourceIdentifier = new ResourceIdentifier($resource_type, $this->entity->uuid());
    $this->links = is_null($links) ? (new LinkCollection([]))->withContext($this) : $links->withContext($this);
    if ($resource_type->isLocatable() && !$resource_type->isInternal() && !$this->links->hasLinkWithKey('self')) {
      $self_link = Url::fromRoute(Routes::getRouteName($this->getResourceType(), 'individual'), ['entity' => $this->getId()]);
      $this->links = $this->links->withLink('self', new Link(new CacheableMetadata(), $self_link, ['self']));
    }
  }

  /**
   * Whether the resource object has the given field.
   *
   * @param string $public_field_name
   *   A public field name.
   *
   * @return bool
   *   TRUE if the resource object has the given field, FALSE otherwise.
   */
  public function hasField($public_field_name) {
    return isset($this->fields[$public_field_name]);
  }

  /**
   * Gets the given field.
   *
   * @param string $public_field_name
   *   A public field name.
   *
   * @return mixed|\Drupal\Core\Field\FieldItemListInterface|null
   *   The field or NULL if the resource object does not have the given field.
   *
   * @see ::extractFields()
   */
  public function getField($public_field_name) {
    return $this->hasField($public_field_name) ? $this->fields[$public_field_name] : NULL;
  }

  /**
   * Gets the ResourceObject's fields.
   *
   * @return mixed|\Drupal\Core\Field\FieldItemListInterface[]
   *   The resource object's fields.
   *
   * @see ::extractFields()
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Gets the ResourceObject's links.
   *
   * @return \Drupal\jsonapi\JsonApiResource\LinkCollection
   *   The resource object's links.
   */
  public function getLinks() {
    return $this->links;
  }

  /**
   * Gets a Url for the ResourceObject.
   *
   * @return \Drupal\Core\Url
   *   The URL for the identified resource object.
   *
   * @throws \LogicException
   *   Thrown if the resource object is not locatable.
   *
   * @see \Drupal\jsonapi\ResourceType\ResourceTypeRepository::isLocatableResourceType()
   */
  public function toUrl() {
    foreach ($this->links as $key => $link) {
      if ($key === 'self') {
        $first = reset($link);
        return $first->getUri();
      }
    }
    throw new \LogicException('A Url does not exist for this resource object because its resource type is not locatable.');
  }

  /**
   * Extracts the entity's fields.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from which fields should be extracted.
   *
   * @return mixed|\Drupal\Core\Field\FieldItemListInterface[]
   *   If the resource object represents a content entity, the fields will be
   *   objects satisfying FieldItemListInterface. If it represents a config
   *   entity, the fields will be scalar values or arrays.
   */
  protected function extractFields(EntityInterface $entity) {
    assert($entity instanceof ContentEntityInterface || $entity instanceof ConfigEntityInterface);
    return $entity instanceof ContentEntityInterface
      ? $this->extractContentEntityFields($entity)
      : $this->extractConfigEntityFields($entity);
  }

  /**
   * Extracts a content entity's fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The config entity from which fields should be extracted.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   The fields extracted from a content entity.
   */
  protected function extractContentEntityFields(ContentEntityInterface $entity) {
    $output = [];
    $fields = TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData());
    // Filter the array based on the field names.
    $enabled_field_names = array_filter(
      array_keys($fields),
      [$this->resourceType, 'isFieldEnabled']
    );

    // The "label" field needs special treatment: some entity types have a label
    // field that is actually backed by a label callback.
    $entity_type = $entity->getEntityType();
    if ($entity_type->hasLabelCallback()) {
      $fields[$this->getLabelFieldName()]->value = $entity->label();
    }

    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $this->resourceType->getPublicName($field_name);
      $output[$public_field_name] = $field_value;
    }
    return $output;
  }

  /**
   * Determines the entity type's (internal) label field name.
   *
   * @return string
   *   The label field name.
   */
  protected function getLabelFieldName() {
    $label_field_name = $this->entity->getEntityType()->getKey('label');
    // @todo Remove this work-around after https://www.drupal.org/project/drupal/issues/2450793 lands.
    if ($this->entity->getEntityTypeId() === 'user') {
      $label_field_name = 'name';
    }
    return $label_field_name;
  }

  /**
   * Extracts a config entity's fields.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
   *   The config entity from which fields should be extracted.
   *
   * @return array
   *   The fields extracted from a config entity.
   */
  protected function extractConfigEntityFields(ConfigEntityInterface $entity) {
    $enabled_public_fields = [];
    $fields = $entity->toArray();
    // Filter the array based on the field names.
    $enabled_field_names = array_filter(array_keys($fields), function ($internal_field_name) {
      // Config entities have "fields" which aren't known to the resource type,
      // these fields should not be excluded because they cannot be enabled or
      // disabled.
      return !$this->resourceType->hasField($internal_field_name) || $this->resourceType->isFieldEnabled($internal_field_name);
    });
    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $this->resourceType->getPublicName($field_name);
      $enabled_public_fields[$public_field_name] = $field_value;
    }
    return $enabled_public_fields;
  }

}
