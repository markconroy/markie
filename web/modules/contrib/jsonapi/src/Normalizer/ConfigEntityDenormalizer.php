<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;

/**
 * Converts the Drupal config entity object to a JSON:API array structure.
 *
 * @internal
 */
final class ConfigEntityDenormalizer extends EntityDenormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = ConfigEntityInterface::class;

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
