<?php

namespace Drupal\jsonapi\ParamConverter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\jsonapi\Routing\Routes;
use Symfony\Component\Routing\Route;

/**
 * Parameter converter for upcasting entity UUIDs to full objects.
 *
 * @see \Drupal\Core\ParamConverter\EntityConverter
 *
 * @todo Remove when https://www.drupal.org/node/2353611 lands.
 *
 * @internal
 */
class EntityUuidConverter extends EntityConverter {

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $entity_type_id = $this->getEntityTypeFromDefaults($definition, $name, $defaults);
    // @see https://www.drupal.org/project/drupal/issues/2624770
    $entity_type_manager = isset($this->entityTypeManager) ? $this->entityTypeManager : $this->entityManager;
    if ($storage = $entity_type_manager->getStorage($entity_type_id)) {
      if (!$entities = $storage->loadByProperties(['uuid' => $value])) {
        return NULL;
      }
      $entity = reset($entities);
      // If the entity type is translatable, ensure we return the proper
      // translation object for the current context.
      if ($entity instanceof EntityInterface && $entity instanceof TranslatableInterface) {
        // @see https://www.drupal.org/project/drupal/issues/2624770
        $entity_repository = isset($this->entityRepository) ? $this->entityRepository : $this->entityManager;
        $entity = $entity_repository->getTranslationFromContext($entity, NULL, ['operation' => 'entity_upcast']);
      }
      return $entity;
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return (
      (bool) Routes::getResourceTypeNameFromParameters($route->getDefaults()) &&
      !empty($definition['type']) && strpos($definition['type'], 'entity') === 0
    );
  }

}
