<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

/**
 * Class RawCurrentPageTitle.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "raw_current_page_title",
 * );
 */
class RawCurrentPageTitle extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current page's title. This could be useful if you want to add
    // the current page title as a breadcrumb.
    $request = \Drupal::request();
    if ($route = $request->attributes->get(RouteObjectInterface::ROUTE_OBJECT)) {
      $title = \Drupal::service('title_resolver')->getTitle($request, $route);
    }
    return empty($title) ? '' : $title;
  }

}
