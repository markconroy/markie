<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Normalizer class specific for entity reference field objects.
 *
 * @internal
 */
class EntityReferenceFieldNormalizer extends FieldNormalizer {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = EntityReferenceFieldItemListInterface::class;

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * Instantiates a EntityReferenceFieldNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   */
  public function __construct(LinkManager $link_manager) {
    $this->linkManager = $link_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    assert($field instanceof EntityReferenceFieldItemListInterface);
    // Build the relationship object based on the Entity Reference and normalize
    // that object instead.
    $definition = $field->getFieldDefinition();
    $cardinality = $definition
      ->getFieldStorageDefinition()
      ->getCardinality();
    $resource_identifiers = array_filter(ResourceIdentifier::toResourceIdentifiers($field->filterEmptyItems()), function (ResourceIdentifierInterface $resource_identifier) {
      return !$resource_identifier->getResourceType()->isInternal();
    });
    $context['field_name'] = $field->getName();
    $normalized_items = CacheableNormalization::aggregate($this->serializer->normalize($resource_identifiers, $format, $context));
    assert($context['resource_object'] instanceof ResourceIdentifierInterface);
    $resource_type = $context['resource_object']->getResourceType();
    $field_name = $resource_type->getPublicName($field->getName());
    $links = $this->getLinks($resource_type, $field_name, $field->getEntity()->uuid());
    $normalization = $normalized_items->getNormalization();
    return (new CacheableNormalization($normalized_items, [
      // Empty 'to-one' relationships must be NULL.
      // Empty 'to-many' relationships must be an empty array.
      // @link http://jsonapi.org/format/#document-resource-object-linkage
      'data' => $cardinality === 1 ? array_shift($normalization) : $normalization,
      'links' => $links,
    ]));
  }

  /**
   * Gets the links for the relationship.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON:API resource type on which the relationship being normalized
   *   resides.
   * @param string $field_name
   *   The field name for the relationship.
   * @param string $host_entity_id
   *   The ID of the entity on which the relationship resides.
   *
   * @return array
   *   An array of links to be rasterized.
   */
  protected function getLinks(ResourceType $resource_type, $field_name, $host_entity_id) {
    $relationship_field_name = $resource_type->getPublicName($field_name);
    $route_parameters = [
      'related' => $relationship_field_name,
    ];
    $links['self']['href'] = $this->linkManager->getEntityLink(
      $host_entity_id,
      $resource_type,
      $route_parameters,
      "$relationship_field_name.relationship.get"
    );
    $resource_types = $resource_type->getRelatableResourceTypesByField($field_name);
    if (static::hasNonInternalResourceType($resource_types)) {
      $links['related']['href'] = $this->linkManager->getEntityLink(
        $host_entity_id,
        $resource_type,
        $route_parameters,
        "$relationship_field_name.related"
      );
    }
    return $links;
  }

  /**
   * Determines if a given list of resource types contains a non-internal type.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceType[] $resource_types
   *   The JSON:API resource types to evaluate.
   *
   * @return bool
   *   FALSE if every resource type is internal, TRUE otherwise.
   */
  protected static function hasNonInternalResourceType(array $resource_types) {
    foreach ($resource_types as $resource_type) {
      if (!$resource_type->isInternal()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
