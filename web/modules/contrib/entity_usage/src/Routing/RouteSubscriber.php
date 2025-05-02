<?php

namespace Drupal\entity_usage\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Registers a route for generic usage local tasks for entities.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config) {
    $this->entityTypeManager = $entity_manager;
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $configured_types = $this->config->get('entity_usage.settings')->get('local_task_enabled_entity_types') ?: [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      // We prefer the canonical template, but we also allow edit-form templates
      // on entities that don't have canonical (like views, etc).
      if ($entity_type->hasLinkTemplate('canonical')) {
        $template = $entity_type->getLinkTemplate('canonical');
      }
      elseif ($entity_type->hasLinkTemplate('edit-form')) {
        $template = $entity_type->getLinkTemplate('edit-form');
      }
      if (empty($template) || !in_array($entity_type_id, $configured_types, TRUE)) {
        continue;
      }
      $options = [
        '_admin_route' => TRUE,
        '_entity_usage_entity_type_id' => $entity_type_id,
        'parameters' => [
          $entity_type_id => [
            'type' => 'entity:' . $entity_type_id,
          ],
        ],
      ];

      $route = new Route(
        $template . '/usage',
        [
          '_controller' => '\Drupal\entity_usage\Controller\LocalTaskUsageController::listUsageLocalTask',
          '_title_callback' => '\Drupal\entity_usage\Controller\LocalTaskUsageController::getTitleLocalTask',
        ],
        [
          '_permission' => 'access entity usage statistics',
          '_custom_access' => '\Drupal\entity_usage\Controller\LocalTaskUsageController::checkAccessLocalTask',
        ],
        $options
      );

      $collection->add("entity.$entity_type_id.entity_usage", $route);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 99];
    return $events;
  }

}
