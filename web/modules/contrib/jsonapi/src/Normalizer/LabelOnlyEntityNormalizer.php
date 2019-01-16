<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\jsonapi\LabelOnlyEntity;
use Drupal\jsonapi\Normalizer\Value\EntityNormalizerValue;
use Drupal\jsonapi\LinkManager\LinkManager;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Pretends that the entity only has a single field: the label field.
 *
 * @see \Drupal\jsonapi\Normalizer\EntityNormalizer::normalize()
 *
 * @internal
 */
class LabelOnlyEntityNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = LabelOnlyEntity::class;

  /**
   * {@inheritdoc}
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
   * Constructs an LabelOnlyEntityNormalizer object.
   *
   * @param \Drupal\jsonapi\LinkManager\LinkManager $link_manager
   *   The link manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(LinkManager $link_manager, ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->linkManager = $link_manager;
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($label_only_entity, $format = NULL, array $context = []) {
    assert($label_only_entity instanceof LabelOnlyEntity);
    $entity = $label_only_entity->getEntity();

    $context['resource_type'] = $this->resourceTypeRepository->get(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    // Determine the (internal) label field name.
    $label_field_name = $label_only_entity->getLabelFieldName();

    // Determine the public alias for the label field name.
    assert($context['resource_type'] instanceof ResourceType);
    $resource_type = $context['resource_type'];
    $public_field_label_name = $resource_type->getPublicName($label_field_name);

    // Perform the default entity normalization, extract all values from the
    // resulting EntityNormalizerValue object.
    // @see \Drupal\jsonapi\Normalizer\EntityNormalizer::normalize()
    $full_normalized_entity = $this->serializer->normalize($entity, $format, $context);
    assert($full_normalized_entity instanceof EntityNormalizerValue);
    $all_values = $full_normalized_entity->getValues();

    // Reconstruct an EntityNormalizerValue object, this time with only the
    // label field.
    $label_only_values = [$public_field_label_name => $all_values[$public_field_label_name]];
    $link_context = ['link_manager' => $this->linkManager];
    return new EntityNormalizerValue($label_only_values, $context, $entity, $link_context);
  }

}
