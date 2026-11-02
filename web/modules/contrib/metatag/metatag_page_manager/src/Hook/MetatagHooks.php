<?php

namespace Drupal\metatag_page_manager\Hook;

use Drupal\page_manager\Entity\PageVariant;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_page_manager.
 */
class MetatagHooks {

  /**
   * Implements hook_metatag_route_entity().
   *
   * @deprecated in metatag:2.2.2 and is removed from metatag:3.0.0. No replacement is available.
   *
   * @see https://www.drupal.org/node/3633239
   */
  #[Hook('metatag_route_entity')]
  public function metatagRouteEntity(RouteMatchInterface $route_match) {
    if ($variant = $route_match->getParameter('page_manager_page_variant')) {
      return $variant;
    }
    if (strpos($route_match->getRouteName(), 'page_manager.page_view') === 0) {
      $page_variant_id = $route_match->getRouteObject()->getDefault('_page_manager_page_variant');
      return PageVariant::load($page_variant_id);
    }
  }

}
