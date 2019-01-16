<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\jsonapi\JsonApiResource\ResourceIdentifier;
use Drupal\jsonapi\Normalizer\Value\RelationshipNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\jsonapi\LinkManager\LinkManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalizes a Relationship according to the JSON:API specification.
 *
 * Normalizer class for relationship elements. A relationship can be anything
 * that points to an entity in a JSON:API resource.
 *
 * @internal
 */
class RelationshipNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = Relationship::class;

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = ['api_json'];

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * RelationshipNormalizer constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, LinkManager $link_manager, EntityFieldManagerInterface $field_manager, EntityRepositoryInterface $entity_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->linkManager = $link_manager;
    $this->fieldManager = $field_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    // If we get here, it's via a relationship POST/PATCH.
    /** @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
    $resource_type = $context['resource_type'];
    $entity_type_id = $resource_type->getEntityTypeId();
    $field_definitions = $this->fieldManager->getFieldDefinitions(
      $entity_type_id,
      $resource_type->getBundle()
    );
    if (empty($context['related']) || empty($field_definitions[$context['related']])) {
      throw new BadRequestHttpException('Invalid or missing related field.');
    }
    /* @var \Drupal\field\Entity\FieldConfig $field_definition */
    $field_definition = $field_definitions[$context['related']];
    // This is typically 'target_id'.
    $item_definition = $field_definition->getItemDefinition();
    $property_key = $item_definition->getMainPropertyName();
    $target_resource_types = $resource_type->getRelatableResourceTypesByField($resource_type->getPublicName($context['related']));
    $target_resource_type_names = array_map(function (ResourceType $resource_type) {
      return $resource_type->getTypeName();
    }, $target_resource_types);

    $is_multiple = $field_definition->getFieldStorageDefinition()->isMultiple();
    $data = $this->massageRelationshipInput($data, $is_multiple);
    $resource_identifiers = array_map(function ($value) use ($property_key, $target_resource_type_names) {
      // Make sure that the provided type is compatible with the targeted
      // resource.
      if (!in_array($value['type'], $target_resource_type_names)) {
        throw new BadRequestHttpException(sprintf(
          'The provided type (%s) does not mach the destination resource types (%s).',
          $value['type'],
          implode(', ', $target_resource_type_names)
        ));
      }
      return new ResourceIdentifier($value['type'], $value['id'], isset($value['meta']) ? $value['meta'] : []);
    }, $data['data']);
    if (!ResourceIdentifier::areResourceIdentifiersUnique($resource_identifiers)) {
      throw new BadRequestHttpException('Duplicate relationships are not permitted. Use `meta.arity` to distinguish resource identifiers with matching `type` and `id` values.');
    }
    return $resource_identifiers;
  }

  /**
   * Validates and massages the relationship input depending on the cardinality.
   *
   * @param array $data
   *   The input data from the body.
   * @param bool $is_multiple
   *   Indicates if the relationship is to-many.
   *
   * @return array
   *   The massaged data array.
   */
  protected function massageRelationshipInput(array $data, $is_multiple) {
    if ($is_multiple) {
      if (!is_array($data['data'])) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
      // Leave the invalid elements.
      $invalid_elements = array_filter($data['data'], function ($element) {
        return empty($element['type']) || empty($element['id']);
      });
      if ($invalid_elements) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
    }
    else {
      // For to-one relationships you can have a NULL value.
      if (is_null($data['data'])) {
        return ['data' => []];
      }
      if (empty($data['data']['type']) || empty($data['data']['id'])) {
        throw new BadRequestHttpException('Invalid body payload for the relationship.');
      }
      $data['data'] = [$data['data']];
    }
    return $data;
  }

  /**
   * Helper function to normalize field items.
   *
   * @param \Drupal\jsonapi\Normalizer\Relationship|object $relationship
   *   The field object.
   * @param string $format
   *   The format.
   * @param array $context
   *   The context array.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\RelationshipNormalizerValue
   *   The array of normalized field items.
   */
  public function normalize($relationship, $format = NULL, array $context = []) {
    /* @var \Drupal\jsonapi\Normalizer\Relationship $relationship */
    $normalizer_items = [];
    foreach ($relationship->getItems() as $relationship_item) {
      // If the relationship points to a disabled resource type, do not add the
      // normalized relationship item.
      if (!$relationship_item->getTargetResourceType()) {
        continue;
      }
      $normalizer_items[] = $this->serializer->normalize($relationship_item, $format, $context);
    }
    $cardinality = $relationship->getCardinality();
    assert($context['resource_type'] instanceof ResourceType);
    $resource_type = $context['resource_type'];
    $link_context = [
      'host_entity_id' => $relationship->getHostEntity()->uuid(),
      'field_name' => $resource_type->getPublicName($relationship->getPropertyName()),
      'link_manager' => $this->linkManager,
      'resource_type' => $resource_type,
    ];
    // If this is called, access to the Relationship field is allowed. The
    // cacheability of the access result is carried by the Relationship value
    // object. Therefore, we can safely construct an access result object here.
    // Access to the targeted related resources will be checked separately.
    // @see \Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer::normalize()
    // @see \Drupal\jsonapi\Normalizer\RelationshipItemNormalizer::normalize()
    $relationship_access = AccessResult::allowed()->addCacheableDependency($relationship);
    return new RelationshipNormalizerValue($relationship_access, $normalizer_items, $cardinality, $link_context);
  }

}
