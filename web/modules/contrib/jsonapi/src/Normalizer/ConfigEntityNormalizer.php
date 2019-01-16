<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\jsonapi\Normalizer\Value\ConfigFieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Converts the Drupal config entity object to a JSON:API array structure.
 *
 * @internal
 */
class ConfigEntityNormalizer extends EntityNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ConfigEntityInterface::class;

  /**
   * {@inheritdoc}
   */
  protected function getFields($entity, $bundle, ResourceType $resource_type) {
    $enabled_public_fields = [];
    $fields = $entity->toArray();
    // Filter the array based on the field names. Some config entity types don't
    // have a complete field mapping available and their fields can't be
    // enabled or disabled. Thus this code should only filter out fields that
    // are known to exist and are not enabled.
    $enabled_field_names = array_filter(array_keys($fields), function ($field_name) use ($resource_type) {
      return !$resource_type->hasField($field_name) || $resource_type->isFieldEnabled($field_name);
    });
    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    /* @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity */
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $resource_type->getPublicName($field_name);
      $enabled_public_fields[$public_field_name] = $field_value;
    }
    return $enabled_public_fields;
  }

  /**
   * {@inheritdoc}
   */
  protected function serializeField($field, array $context, $format) {
    return new FieldNormalizerValue(
      // Config entities have no concept of "fields", nor any concept of
      // "field access". For practical reasons, JSON:API uses the same value
      // object that it uses for content entities (FieldNormalizerValue), and
      // that requires an access result. Therefore we can safely hardcode it.
      AccessResult::allowed(),
      [new ConfigFieldItemNormalizerValue($field)],
      1,
      'attributes'
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareInput(array $data, ResourceType $resource_type, $format, array $context) {
    $prepared = [];
    foreach ($data as $key => $value) {
      $prepared[$resource_type->getInternalName($key)] = $value;
    }
    return $prepared;
  }

}
