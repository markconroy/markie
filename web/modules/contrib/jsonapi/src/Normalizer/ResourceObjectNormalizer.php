<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\JsonApiResource\ResourceObject;
use Drupal\jsonapi\Normalizer\Value\CacheableNormalization;
use Drupal\jsonapi\Normalizer\Value\CacheableOmission;

/**
 * Converts the JSON:API module ResourceObject into a JSON:API array structure.
 *
 * @internal
 */
class ResourceObjectNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ResourceObject::class;

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    assert($object instanceof ResourceObject);
    // If the fields to use were specified, only output those field values.
    $context['resource_object'] = $object;
    $resource_type = $object->getResourceType();
    $resource_type_name = $resource_type->getTypeName();
    $fields = $object->getFields();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = array_keys($fields);
    }
    $normalizer_values = [];
    foreach ($fields as $field_name => $field) {
      $in_sparse_fieldset = in_array($field_name, $field_names);
      // Omit fields not listed in sparse fieldsets.
      if (!$in_sparse_fieldset) {
        continue;
      }
      $normalizer_values[$field_name] = $this->serializeField($field, $context, $format);
    }
    // Create the array of normalized fields.
    $normalized = [
      'type' => $resource_type->getTypeName(),
      'id' => $object->getId(),
    ];
    $links = $this->serializer->normalize($object->getLinks(), $format, $context);
    assert($links instanceof CacheableNormalization);
    $normalized['links'] = $links->getNormalization();
    $relationship_field_names = array_keys($resource_type->getRelatableResourceTypes());
    $attributes = CacheableNormalization::aggregate(array_diff_key($normalizer_values, array_flip($relationship_field_names)));
    $relationships = CacheableNormalization::aggregate(array_intersect_key($normalizer_values, array_flip($relationship_field_names)));
    $normalized['attributes'] = $attributes->getNormalization();
    $normalized['relationships'] = $relationships->getNormalization();
    return (new CacheableNormalization($object, array_filter($normalized)))->withCacheableDependency($attributes)->withCacheableDependency($relationships)->withCacheableDependency($links);
  }

  /**
   * Serializes a given field.
   *
   * @param mixed $field
   *   The field to serialize.
   * @param array $context
   *   The normalization context.
   * @param string $format
   *   The serialization format.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\CacheableNormalization
   *   The normalized value.
   */
  protected function serializeField($field, array $context, $format) {
    // Only content entities contain FieldItemListInterface fields. Since config
    // entities do not have "real" fields and therefore do not have field access
    // restrictions.
    if ($field instanceof FieldItemListInterface) {
      $field_access_result = $field->access('view', $context['account'], TRUE);
      if (!$field_access_result->isAllowed()) {
        return new CacheableOmission(CacheableMetadata::createFromObject($field_access_result));
      }
      $normalized_field = $this->serializer->normalize($field, $format, $context);
      assert($normalized_field instanceof CacheableNormalization);
      return $normalized_field->withCacheableDependency(CacheableMetadata::createFromObject($field_access_result));
    }
    else {
      // Config "fields" in this case are arrays or primitives and do not need
      // to be normalized.
      return new CacheableNormalization(new CacheableMetadata(), $field);
    }
  }

}
