<?php

namespace Drupal\entity_usage;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;

/**
 * A route enhancer to stop entity loads by ParamConversionEnhancer::enhance().
 *
 * @see \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer::enhance()
 */
class OptimizedRouteEnhancer implements EnhancerInterface {

  public const ROUTE_ATTRIBUTE = 'entity_usage.optimized_route_enhancer';

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    // Just run the parameter conversion once per request.
    if ($request->attributes->get(static::ROUTE_ATTRIBUTE) && !isset($defaults['_raw_variables'])) {
      $defaults['_raw_variables'] = $this->copyRawVariables($defaults);
    }
    return $defaults;
  }

  /**
   * Store a backup of the raw values corresponding to the route pattern.
   *
   * Note: this is a copy of core code and should be kept in sync with
   * \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer::copyRawVariables().
   *
   * @param mixed[] $defaults
   *   The route defaults array.
   *
   * @return \Symfony\Component\HttpFoundation\InputBag
   *   The input bag container with the raw variables.
   *
   * @see \Drupal\Core\Routing\Enhancer\ParamConversionEnhancer::copyRawVariables()
   */
  protected function copyRawVariables(array $defaults) {
    /** @var \Symfony\Component\Routing\Route $route */
    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    $variables = array_flip($route->compile()->getVariables());
    // Foreach will copy the values from the array it iterates. Even if they
    // are references, use it to break them. This avoids any scenarios where raw
    // variables also get replaced with converted values.
    $raw_variables = [];
    foreach (array_intersect_key($defaults, $variables) as $key => $value) {
      $raw_variables[$key] = $value;
    }
    // Route defaults that do not start with a leading "_" are also
    // parameters, even if they are not included in path or host patterns.
    foreach ($route->getDefaults() as $name => $value) {
      if (!isset($raw_variables[$name]) && !str_starts_with($name, '_')) {
        $raw_variables[$name] = $value;
      }
    }
    return new InputBag($raw_variables);
  }

}
