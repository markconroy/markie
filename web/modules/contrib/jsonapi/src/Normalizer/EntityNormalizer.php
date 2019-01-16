<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\TypedData\TypedDataInternalPropertiesHelper;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\jsonapi\Normalizer\Value\FieldNormalizerValueInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Converts the Drupal entity object to a JSON:API array structure.
 *
 * @internal
 */
class EntityNormalizer extends NormalizerBase implements DenormalizerInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ContentEntityInterface::class;

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = ['api_json'];

  /**
   * The link manager.
   *
   * @var \Drupal\jsonapi\LinkManager\LinkManager
   */
  protected $linkManager;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * The field plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $pluginManager;

  /**
   * Constructs an EntityNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager
   *   The plugin manager for fields.
   */
  public function __construct(LinkManager $link_manager, ResourceTypeRepositoryInterface $resource_type_repository, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $plugin_manager) {
    $this->linkManager = $link_manager;
    $this->resourceTypeRepository = $resource_type_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldManager = $field_manager;
    $this->pluginManager = $plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    // If the fields to use were specified, only output those field values.
    $context['resource_type'] = $resource_type = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );
    $resource_type_name = $resource_type->getTypeName();
    // Get the bundle ID of the requested resource. This is used to determine if
    // this is a bundle level resource or an entity level resource.
    $bundle = $resource_type->getBundle();
    if (!empty($context['sparse_fieldset'][$resource_type_name])) {
      $field_names = $context['sparse_fieldset'][$resource_type_name];
    }
    else {
      $field_names = $this->getFieldNames($entity, $bundle, $resource_type);
    }
    /* @var Value\FieldNormalizerValueInterface[] $normalizer_values */
    $normalizer_values = [];
    foreach ($this->getFields($entity, $bundle, $resource_type) as $field_name => $field) {
      $in_sparse_fieldset = in_array($field_name, $field_names);
      // Omit fields not listed in sparse fieldsets.
      if (!$in_sparse_fieldset) {
        continue;
      }
      $normalized_field = $this->serializeField($field, $context, $format);
      assert($normalized_field instanceof FieldNormalizerValueInterface);
      $normalizer_values[$field_name] = $normalized_field;
    }

    $link_context = ['link_manager' => $this->linkManager];
    return new EntityNormalizerValue($normalizer_values, $context, $entity, $link_context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (empty($context['resource_type']) || !$context['resource_type'] instanceof ResourceType) {
      throw new PreconditionFailedHttpException('Missing context during denormalization.');
    }
    /* @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
    $resource_type = $context['resource_type'];
    $entity_type_id = $resource_type->getEntityTypeId();
    $bundle = $resource_type->getBundle();
    $bundle_key = $this->entityTypeManager->getDefinition($entity_type_id)
      ->getKey('bundle');
    if ($bundle_key && $bundle) {
      $data[$resource_type->getPublicName($bundle_key)] = $bundle;
    }

    return $this->entityTypeManager->getStorage($entity_type_id)
      ->create($this->prepareInput($data, $resource_type, $format, $context));
  }

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   * @param string $bundle
   *   The entity bundle.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type.
   *
   * @return string[]
   *   The field names.
   */
  protected function getFieldNames($entity, $bundle, ResourceType $resource_type) {
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    return array_keys($this->getFields($entity, $bundle, $resource_type));
  }

  /**
   * Gets the field names for the given entity.
   *
   * @param mixed $entity
   *   The entity.
   * @param string $bundle
   *   The bundle id.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type.
   *
   * @return array
   *   The fields.
   */
  protected function getFields($entity, $bundle, ResourceType $resource_type) {
    $output = [];
    $fields = TypedDataInternalPropertiesHelper::getNonInternalProperties($entity->getTypedData());
    // Filter the array based on the field names.
    $enabled_field_names = array_filter(
      array_keys($fields),
      [$resource_type, 'isFieldEnabled']
    );

    // The "label" field needs special treatment: some entity types have a label
    // field that is actually backed by a label callback.
    $entity_type = $entity->getEntityType();
    if ($entity_type->hasLabelCallback()) {
      $label_field_name = $entity_type->getKey('label');
      // @todo Remove this work-around after https://www.drupal.org/project/drupal/issues/2450793 lands.
      if ($entity->getEntityTypeId() === 'user') {
        $label_field_name = 'name';
      }
      $fields[$label_field_name]->value = $entity->label();
    }

    // Return a sub-array of $output containing the keys in $enabled_fields.
    $input = array_intersect_key($fields, array_flip($enabled_field_names));
    /* @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    foreach ($input as $field_name => $field_value) {
      $public_field_name = $resource_type->getPublicName($field_name);
      $output[$public_field_name] = $field_value;
    }
    return $output;
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
   * @return Value\FieldNormalizerValueInterface
   *   The normalized value.
   */
  protected function serializeField($field, array $context, $format) {
    return $this->serializer->normalize($field, $format, $context);
  }

  /**
   * Prepares the input data to create the entity.
   *
   * @param array $data
   *   The input data to modify.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   Contains the info about the resource type.
   * @param string $format
   *   Format the given data was extracted from.
   * @param array $context
   *   Options available to the denormalizer.
   *
   * @return array
   *   The modified input data.
   */
  protected function prepareInput(array $data, ResourceType $resource_type, $format, array $context) {
    $data_internal = [];

    $field_map = $this->fieldManager->getFieldMap()[$resource_type->getEntityTypeId()];

    $entity_type_id = $resource_type->getEntityTypeId();
    $entity_type_definition = $this->entityTypeManager->getDefinition($entity_type_id);
    $uuid_key = $entity_type_definition->getKey('uuid');

    // Translate the public fields into the entity fields.
    foreach ($data as $public_field_name => $field_value) {
      $internal_name = $resource_type->getInternalName($public_field_name);

      // Fail for any disabled field unless it is the uuid key, which is
      // disabled because it's transmitted as the `id` key of a resource object.
      // However, for the purpose of denormalization, it exists in this array.
      // @see \Drupal\jsonapi\ResourceType\ResourceTypeRepository::getFieldMapping()
      if (!$resource_type->isFieldEnabled($internal_name) && $uuid_key !== $internal_name) {
        throw new UnprocessableEntityHttpException(sprintf(
          'The attribute %s does not exist on the %s resource type.',
          $internal_name,
          $resource_type->getTypeName()
        ));
      }

      $field_type = $field_map[$internal_name]['type'];
      $field_class = $this->pluginManager->getDefinition($field_type)['list_class'];

      $field_denormalization_context = array_merge($context, [
        'field_type' => $field_type,
        'field_name' => $internal_name,
        'field_definition' => $this->fieldManager->getFieldDefinitions($resource_type->getEntityTypeId(), $resource_type->getBundle())[$internal_name],
      ]);
      $data_internal[$internal_name] = $this->serializer->denormalize($field_value, $field_class, $format, $field_denormalization_context);
    }

    return $data_internal;
  }

}
