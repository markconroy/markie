<?php

declare(strict_types=1);

namespace Drupal\ai_translate_overview_test\Routing;

use Drupal\ai_translate_overview_test\Controller\ReplacementOverviewController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Replaces the translation overview controller of entity_test_mul.
 *
 * This is what a module such as Canvas Translate does for its own entities.
 */
class ReplacementOverviewRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $collection->get('entity.entity_test_mul.content_translation_overview')
      ?->setDefault('_controller', ReplacementOverviewController::class . '::overview');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After Content Translation adds the overview routes (-210), and before
    // AI Translate wraps their controllers (-230).
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }

}
