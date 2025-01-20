<?php

namespace Drupal\ai_automators\Routing;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

/**
 * Defines dynamic routes.
 */
class AutomatorRouteSubscriber implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new AutomatorRouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Provides dynamic routes.
   */
  public function routes(): array {
    $routes = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements('Drupal\Core\Config\Entity\ConfigEntityInterface')) {
        continue;
      }
      if (!$entity_type->getBundleOf()) {
        continue;
      }
      $route = new Route(
        '/admin/structure/types/manage/automator_chain/' . $entity_type->getBundleOf() . '/{' . $entity_type_id . '}',
        [
          '_form' => '\Drupal\ai_automators\Form\AiChainForm',
          '_title' => 'AI Automator Run Order',
        ],
        [
          '_permission'  => 'administer ai_automator',
        ]
      );
      $routes['ai_automator.config_chain.' . $entity_type->getBundleOf()] = $route;
    }
    return $routes;
  }

}
