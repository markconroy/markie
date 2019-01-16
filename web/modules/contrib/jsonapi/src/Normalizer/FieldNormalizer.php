<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\NullFieldNormalizerValue;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal field structure to a JSON:API array structure.
 *
 * @internal
 */
class FieldNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = FieldItemListInterface::class;

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = ['api_json'];

  /**
   * {@inheritdoc}
   */
  public function normalize($field, $format = NULL, array $context = []) {
    /* @var \Drupal\Core\Field\FieldItemListInterface $field */

    $access = $field->access('view', $context['account'], TRUE);
    $property_type = static::isRelationship($field) ? 'relationships' : 'attributes';

    if ($access->isAllowed()) {
      $normalized_field_items = $this->normalizeFieldItems($field, $format, $context);
      assert(Inspector::assertAll(function ($v) {
        return $v instanceof FieldItemNormalizerValue;
      }, $normalized_field_items));

      $cardinality = $field->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality();
      return new FieldNormalizerValue($access, $normalized_field_items, $cardinality, $property_type);
    }
    else {
      return new NullFieldNormalizerValue($access, $property_type);
    }
  }

  /**
   * Checks if the passed field is a relationship field.
   *
   * @param mixed $field
   *   The field.
   *
   * @return bool
   *   TRUE if it's a JSON:API relationship.
   */
  protected static function isRelationship($field) {
    return $field instanceof EntityReferenceFieldItemList || $field instanceof Relationship;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    $field_definition = $context['field_definition'];
    assert($field_definition instanceof FieldDefinitionInterface);

    // If $data contains items (recognizable by numerical array keys, which
    // Drupal's Field API calls "deltas"), then it already is itemized; it's not
    // using the simplified JSON structure that JSON:API generates.
    $is_already_itemized = is_array($data) && array_reduce(array_keys($data), function ($carry, $index) {
      return $carry && is_numeric($index);
    }, TRUE);

    $itemized_data = $is_already_itemized
      ? $data
      : [0 => $data];

    // Single-cardinality fields don't need itemization.
    $field_item_class = $field_definition->getItemDefinition()->getClass();
    if (count($itemized_data) === 1 && $field_definition->getFieldStorageDefinition()->getCardinality() === 1) {
      return $this->serializer->denormalize($itemized_data[0], $field_item_class, $format, $context);
    }

    $data_internal = [];
    foreach ($itemized_data as $delta => $field_item_value) {
      $data_internal[$delta] = $this->serializer->denormalize($field_item_value, $field_item_class, $format, $context);
    }

    return $data_internal;
  }

  /**
   * Helper function to normalize field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The field object.
   * @param string $format
   *   The format.
   * @param array $context
   *   The context array.
   *
   * @return \Drupal\jsonapi\Normalizer\Value\FieldItemNormalizerValue[]
   *   The array of normalized field items.
   */
  protected function normalizeFieldItems(FieldItemListInterface $field, $format, array $context) {
    $normalizer_items = [];
    if (!$field->isEmpty()) {
      foreach ($field as $field_item) {
        $normalizer_items[] = $this->serializer->normalize($field_item, $format, $context);
      }
    }
    return $normalizer_items;
  }

}
