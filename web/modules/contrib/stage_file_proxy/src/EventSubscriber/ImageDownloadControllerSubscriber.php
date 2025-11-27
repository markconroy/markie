<?php

declare(strict_types=1);

namespace Drupal\stage_file_proxy\EventSubscriber;

use Drupal\Core\Routing\RouteBuildEvent;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\stage_file_proxy\Controller\ImageStyleDownloadController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Decorates core's image download controller with our own.
 */
class ImageDownloadControllerSubscriber implements EventSubscriberInterface {

  /**
   * Overwrite the _controller key to point to our controller.
   *
   * @param \Drupal\Core\Routing\RouteBuildEvent $event
   *   The event containing the route being built.
   */
  public function onAlterDecorateController(RouteBuildEvent $event): void {
    $to_alter = [
      'image.style_public',
      'image.style_private',
    ];
    foreach ($to_alter as $name) {
      $definition = $event->getRouteCollection()->get($name);
      if ($definition) {
        $definition->setDefault('_controller', ImageStyleDownloadController::class . "::deliver");
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[RoutingEvents::ALTER] = 'onAlterDecorateController';
    return $events;
  }

}
