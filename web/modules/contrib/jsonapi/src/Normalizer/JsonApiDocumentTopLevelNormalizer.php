<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\ErrorCollection;
use Drupal\jsonapi\Normalizer\Value\JsonApiDocumentTopLevelNormalizerValue;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Normalizes the top-level document according to the JSON:API specification.
 *
 * @see \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel
 *
 * @internal
 */
class JsonApiDocumentTopLevelNormalizer extends NormalizerBase implements DenormalizerInterface, NormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = JsonApiDocumentTopLevel::class;

  /**
   * The link manager to get the links.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Constructs a JsonApiDocumentTopLevelNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager to get the links.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(LinkManager $link_manager, EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->linkManager = $link_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $resource_type = $context['resource_type'];

    // Validate a few common errors in document formatting.
    static::validateRequestBody($data, $resource_type);

    $normalized = [];

    if (!empty($data['data']['attributes'])) {
      $normalized = $data['data']['attributes'];
    }

    if (!empty($data['data']['id'])) {
      $uuid_key = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId())->getKey('uuid');
      $normalized[$uuid_key] = $data['data']['id'];
    }

    if (!empty($data['data']['relationships'])) {
      // Turn all single object relationship data fields into an array of
      // objects.
      $relationships = array_map(function ($relationship) {
        if (isset($relationship['data']['type']) && isset($relationship['data']['id'])) {
          return ['data' => [$relationship['data']]];
        }
        else {
          return $relationship;
        }
      }, $data['data']['relationships']);

      // Get an array of ids for every relationship.
      $relationships = array_map(function ($relationship) {
        if (empty($relationship['data'])) {
          return [];
        }
        if (empty($relationship['data'][0]['id'])) {
          throw new BadRequestHttpException("No ID specified for related resource");
        }
        $id_list = array_column($relationship['data'], 'id');
        if (empty($relationship['data'][0]['type'])) {
          throw new BadRequestHttpException("No type specified for related resource");
        }
        if (!$resource_type = $this->resourceTypeRepository->getByTypeName($relationship['data'][0]['type'])) {
          throw new BadRequestHttpException("Invalid type specified for related resource: '" . $relationship['data'][0]['type'] . "'");
        }

        $entity_type_id = $resource_type->getEntityTypeId();
        try {
          $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
        }
        catch (PluginNotFoundException $e) {
          throw new BadRequestHttpException("Invalid type specified for related resource: '" . $relationship['data'][0]['type'] . "'");
        }
        // In order to maintain the order ($delta) of the relationships, we need
        // to load the entities and create a mapping between id and uuid.
        $related_entities = array_values($entity_storage->loadByProperties(['uuid' => $id_list]));
        $map = [];
        foreach ($related_entities as $related_entity) {
          $map[$related_entity->uuid()] = $related_entity->id();
        }

        // $id_list has the correct order of uuids. We stitch this together with
        // $map which contains loaded entities, and then bring in the correct
        // meta values from the relationship, whose deltas match with $id_list.
        $canonical_ids = [];
        foreach ($id_list as $delta => $uuid) {
          if (!isset($map[$uuid])) {
            // @see \Drupal\jsonapi\Normalizer\EntityReferenceFieldNormalizer::normalize()
            if ($uuid === 'virtual') {
              continue;
            }
            throw new NotFoundHttpException(sprintf('The resource identified by `%s:%s` (given as a relationship item) could not be found.', $relationship['data'][$delta]['type'], $uuid));
          }
          $reference_item = [
            'target_id' => $map[$uuid],
          ];
          if (isset($relationship['data'][$delta]['meta'])) {
            $reference_item += $relationship['data'][$delta]['meta'];
          }
          $canonical_ids[] = $reference_item;
        }

        return array_filter($canonical_ids);
      }, $relationships);

      // Add the relationship ids.
      $normalized = array_merge($normalized, $relationships);
    }
    // Override deserialization target class with the one in the ResourceType.
    $class = $context['resource_type']->getDeserializationTargetClass();

    return $this
      ->serializer
      ->denormalize($normalized, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $serializer = $this->serializer;

    $data = $object->getData();

    if ($data instanceof ErrorCollection) {
      $normalizer_values = array_map(function (HttpExceptionInterface $exception) use ($format, $context, $serializer) {
        return $serializer->normalize($exception, $format, $context);
      }, (array) $data->getIterator());
      return new JsonApiDocumentTopLevelNormalizerValue(JsonApiDocumentTopLevelNormalizerValue::ERROR_DOCUMENT, $normalizer_values, [], FALSE, $object->getMeta());
    }

    $includes = $omissions = [];
    foreach ($object->getIncludes() as $include) {
      $include instanceof EntityAccessDeniedHttpException
        ? $omissions[] = $serializer->normalize($include, $format, $context)
        : $includes[] = $serializer->normalize($include, $format, $context);
    }

    if ($data instanceof EntityReferenceFieldItemListInterface) {
      $normalizer_values = [
        $this->serializer->normalize($data, $format, $context),
      ];

      if (!empty($omissions)) {
        $normalizer_values = array_merge($normalizer_values, $omissions);
      }

      // RelationshipNormalizerValues already handle single vs multiple
      // multiple cardinality fields.
      $cardinality = 1;
      return new JsonApiDocumentTopLevelNormalizerValue(JsonApiDocumentTopLevelNormalizerValue::RESOURCE_OBJECT_DOCUMENT, $normalizer_values, [], $cardinality, $includes, $object->getMeta());
    }
    $is_collection = $data instanceof EntityCollection;
    // To improve the logical workflow deal with an array at all times.
    $entities = $is_collection ? $data->toArray() : [$data];
    $normalizer_values = array_map(function ($entity) use ($format, $context, $serializer) {
      return $serializer->normalize($entity, $format, $context);
    }, $entities);

    if (!empty($omissions)) {
      $normalizer_values = array_merge($normalizer_values, $omissions);
    }

    $cardinality = $is_collection ? $data->getCardinality() : 1;
    return new JsonApiDocumentTopLevelNormalizerValue(JsonApiDocumentTopLevelNormalizerValue::RESOURCE_OBJECT_DOCUMENT, $normalizer_values, $object->getLinks(), $cardinality, $includes, $object->getMeta());
  }

  /**
   * Performs mimimal validation of the document.
   */
  protected static function validateRequestBody(array $document, ResourceType $resource_type) {
    // Ensure that the relationships key was not placed in the top level.
    if (isset($document['relationships']) && !empty($document['relationships'])) {
      throw new BadRequestHttpException("Found \"relationships\" within the document's top level. The \"relationships\" key must be within resource object.");
    }
    // Ensure that the resource object contains the "type" key.
    if (!isset($document['data']['type'])) {
      throw new BadRequestHttpException("Resource object must include a \"type\".");
    }
    // Ensure that the client provided ID is a valid UUID.
    if (isset($document['data']['id']) && !Uuid::isValid($document['data']['id'])) {
      throw new UnprocessableEntityHttpException('IDs should be properly generated and formatted UUIDs as described in RFC 4122.');
    }
    // Ensure that no relationship fields are being set via the attributes
    // resource object member.
    if (isset($document['data']['attributes'])) {
      $received_attribute_field_names = array_keys($document['data']['attributes']);
      $relationship_field_names = array_keys($resource_type->getRelatableResourceTypes());
      if ($relationship_fields_sent_as_attributes = array_intersect($received_attribute_field_names, $relationship_field_names)) {
        throw new UnprocessableEntityHttpException(sprintf("The following relationship fields were provided as attributes: [ %s ]", implode(', ', $relationship_fields_sent_as_attributes)));
      }
    }
  }

}
