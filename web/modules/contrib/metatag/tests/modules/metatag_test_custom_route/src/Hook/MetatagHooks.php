<?php

namespace Drupal\metatag_test_custom_route\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Metatag hook implementations for metatag_test_custom_route.
 */
class MetatagHooks {

  /**
   * Implements hook_metatag_route_entity().
   */
  #[Hook('metatag_route_entity')]
  public function metatagRouteEntity(RouteMatchInterface $route_match) {
    if ($route_match->getRouteName() === 'metatag_test_custom_route.entity_route') {
      if ($entity_test = $route_match->getParameter('entity_test')) {
        return $entity_test;
      }
    }
  }

}
