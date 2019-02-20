<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\jsonapi\Exception\EntityAccessDeniedHttpException;
use Drupal\jsonapi\JsonApiResource\ErrorCollection;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\JsonApiSpec;
use Drupal\jsonapi\Normalizer\Value\HttpExceptionNormalizerValue;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ResourceTypeRepositoryInterface $resource_type_repository) {
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
        $uuid_key = $this->entityTypeManager
          ->getDefinition($entity_type_id)->getKey('uuid');
        $related_entities = array_values($entity_storage->loadByProperties([$uuid_key => $id_list]));
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
    $data = $object->getData();
    if ($data instanceof ErrorCollection) {
      $normalized = $this->normalizeErrorDocument($object, $format, $context);
    }
    elseif ($data instanceof EntityReferenceFieldItemListInterface) {
      $normalized = $this->normalizeEntityReferenceFieldItemList($object, $format, $context);
    }
    else {
      $normalized = $this->normalizeEntityCollection($object, $format, $context);
    }
    // Every JSON:API document contains absolute URLs.
    return $normalized->withCacheableDependency((new CacheableMetadata())->addCacheContexts(['url.site']));
  }

  /**
   * Normalizes an error collection.
   *
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document to normalize.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   */
  protected function normalizeErrorDocument(JsonApiDocumentTopLevel $document, $format, array $context = []) {
    $data = $document->getData();
    $normalizer_values = array_map(function (HttpExceptionInterface $exception) use ($format, $context) {
      return $this->serializer->normalize($exception, $format, $context);
    }, (array) $data->getIterator());
    return $this->normalizeValues($document, $normalizer_values, $format, $context);
  }

  /**
   * Normalizes an entity reference field, i.e. a relationship document.
   *
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document to normalize.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   */
  protected function normalizeEntityReferenceFieldItemList(JsonApiDocumentTopLevel $document, $format, array $context = []) {
    $data = $document->getData();
    $parent_entity = $data->getEntity();
    $resource_type = $this->resourceTypeRepository->get($parent_entity->getEntityTypeId(), $parent_entity->bundle());
    $context['resource_object'] = new ResourceObject($resource_type, $parent_entity);
    $normalizer_values = [
      $this->serializer->normalize($data, $format, $context),
    ];
    unset($context['resource_object']);
    return $this->normalizeValues($document, $normalizer_values, $format, $context);
  }

  /**
   * Normalizes an entity collection, i.e. an individual or collection document.
   *
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document to normalize.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   */
  protected function normalizeEntityCollection(JsonApiDocumentTopLevel $document, $format, array $context = []) {
    $data = $document->getData();
    $is_collection = $data instanceof EntityCollection;
    // To improve the logical workflow deal with an array at all times.
    $resource_objects = $is_collection ? $data->toArray() : [$data];
    $normalizer_values = array_map(function ($entity) use ($format, $context) {
      return $this->serializer->normalize($entity, $format, $context);
    }, $resource_objects);
    return $this->normalizeValues($document, $normalizer_values, $format, $context);
  }

  /**
   * Normalizes a separates accessible includes and inaccessible omissions.
   *
   * @param \Drupal\jsonapi\JsonApiResource\EntityCollection $collection
   *   The includes entity collection.
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return array
   *   A tuple whose first value is an array of normalized entities to be
   *   included and whose second value is an array of normalized
   *   EntityAccessDeniedExceptions to be omitted.
   */
  protected function normalizeIncludesAndOmissions(EntityCollection $collection, $format, array $context = []) {
    $includes = $omissions = [];
    /* @var \Drupal\jsonapi\JsonApiResource\ResourceIdentifierInterface $resource_object */
    foreach ($collection as $resource_object) {
      $resource_object instanceof EntityAccessDeniedHttpException
        ? $omissions[] = $this->serializer->normalize($resource_object, $format, $context)
        : $includes[] = $this->serializer->normalize($resource_object, $format, $context);
    }
    return [$includes, $omissions];
  }

  /**
   * Normalizes a document and its normalizer values.
   *
   * @param \Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel $document
   *   The document object.
   * @param \Drupal\jsonapi\Normalizer\Value\CacheableNormalization[] $normalizer_values
   *   The document's normalized error/data object(s).
   * @param string $format
   *   The normalization format.
   * @param array $context
   *   The normalization context.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized document.
   */
  protected function normalizeValues(JsonApiDocumentTopLevel $document, array $normalizer_values, $format, array $context = []) {
    $is_error_document = $document->getData() instanceof ErrorCollection;
    // Determine which of the two mutually exclusive top-level document members
    // should be used.
    $mutually_exclusive_member = $is_error_document ? 'errors' : 'data';
    $rasterized = [
      $mutually_exclusive_member => [],
      'jsonapi' => [
        'version' => JsonApiSpec::SUPPORTED_SPECIFICATION_VERSION,
        'meta' => [
          'links' => [
            'self' => [
              'href' => JsonApiSpec::SUPPORTED_SPECIFICATION_PERMALINK,
            ],
          ],
        ],
      ],
    ];
    if (!empty($document->getMeta())) {
      $rasterized['meta'] = $document->getMeta();
    }

    $cacheability = new CacheableMetadata();
    array_walk($normalizer_values, [$cacheability, 'addCacheableDependency']);

    if ($is_error_document) {
      foreach ($normalizer_values as $normalized_exception) {
        $rasterized['errors'] = array_merge($rasterized['errors'], $normalized_exception->getNormalization());
      }
      return new CacheableNormalization($cacheability, $rasterized);
    }

    list($includes, $omissions) = $this->normalizeIncludesAndOmissions($document->getIncludes(), $format, $context);
    array_walk($includes, [$cacheability, 'addCacheableDependency']);
    array_walk($omissions, [$cacheability, 'addCacheableDependency']);

    if (!empty($omissions)) {
      $normalizer_values = array_merge($normalizer_values, $omissions);
    }

    $links = $this->serializer->normalize($document->getLinks(), $format, $context);
    $rasterized['links'] = $links->getNormalization();
    $cacheability->addCacheableDependency($links);

    $link_hash_salt = Crypt::randomBytesBase64();
    foreach ($normalizer_values as $normalizer_value) {
      if ($normalizer_value instanceof HttpExceptionNormalizerValue) {
        if (!isset($rasterized['meta']['omitted'])) {
          $rasterized['meta']['omitted'] = [
            'detail' => 'Some resources have been omitted because of insufficient authorization.',
            'links' => [
              'help' => [
                'href' => 'https://www.drupal.org/docs/8/modules/json-api/filtering#filters-access-control',
              ],
            ],
          ];
        }
        // Add the errors to the pre-existing errors.
        foreach ($normalizer_value->getNormalization() as $error) {
          // JSON:API links cannot be arrays and the spec generally favors link
          // relation types as keys. 'item' is the right link relation type, but
          // we need multiple values. To do that, we generate a meaningless,
          // random value to use as a unique key. That value is a hash of a
          // random salt and the link href. This ensures that the key is non-
          // deterministic while letting use deduplicate the links by their
          // href. The salt is *not* used for any cryptographic reason.
          $link_key = 'item:' . static::getLinkHash($link_hash_salt, $error['links']['via']['href']);
          $rasterized['meta']['omitted']['links'][$link_key] = [
            'href' => $error['links']['via']['href'],
            'meta' => [
              'rel' => 'item',
              'detail' => $error['detail'],
            ],
          ];
        }
      }
      else {
        $rasterized_value = $normalizer_value->getNormalization();
        if (array_key_exists('data', $rasterized_value) && array_key_exists('links', $rasterized_value)) {
          $rasterized['data'][] = $rasterized_value['data'];
          $rasterized['links'] = NestedArray::mergeDeep($rasterized['links'], $rasterized_value['links']);
        }
        else {
          $rasterized['data'][] = $rasterized_value;
        }
      }
    }
    // Deal with the single entity case.
    if ($document->getData() instanceof EntityCollection && $document->getData()->getCardinality() !== 1) {
      $rasterized['data'] = array_filter($rasterized['data']);
    }
    else {
      $rasterized['data'] = empty($rasterized['data']) ? NULL : reset($rasterized['data']);
    }

    if ($includes) {
      $rasterized['included'] = array_map(function (CacheableNormalization $include) {
        return $include->getNormalization();
      }, $includes);
    }

    if (empty($rasterized['links'])) {
      unset($rasterized['links']);
    }

    return new CacheableNormalization($cacheability, $rasterized);
  }

  /**
   * Performs minimal validation of the document.
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

  /**
   * Hashes an omitted link.
   *
   * @param string $salt
   *   A hash salt.
   * @param string $link_href
   *   The omitted link.
   *
   * @return string
   *   A 7 character hash.
   */
  protected static function getLinkHash($salt, $link_href) {
    return substr(str_replace(['-', '_'], '', Crypt::hashBase64($salt . $link_href)), 0, 7);
  }

}
