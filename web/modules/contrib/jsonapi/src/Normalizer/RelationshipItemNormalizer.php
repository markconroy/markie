<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\jsonapi\Normalizer\Value\RelationshipItemNormalizerValue;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;

/**
 * Converts the Drupal entity reference item object to a JSON:API structure.
 *
 * @internal
 */
class RelationshipItemNormalizer extends FieldItemNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = RelationshipItem::class;

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * Instantiates a RelationshipItemNormalizer object.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The JSON:API resource type repository.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository) {
    $this->resourceTypeRepository = $resource_type_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($relationship_item, $format = NULL, array $context = []) {
    /* @var $relationship_item \Drupal\jsonapi\Normalizer\RelationshipItem */
    // TODO: We are always loading the referenced entity. Even if it is not
    // going to be included. That may be a performance issue. We do it because
    // we need to know the entity type and bundle to load the JSON:API resource
    // type for the relationship item. We need a better way of finding about
    // this.
    $values = $relationship_item->getValue();
    if (isset($context['langcode'])) {
      $values['lang'] = $context['langcode'];
    }

    return new RelationshipItemNormalizerValue(
      $values,
      new CacheableMetadata(),
      $relationship_item->getTargetResourceType()
    );
  }

}
