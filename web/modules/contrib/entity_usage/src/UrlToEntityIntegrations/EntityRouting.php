<?php

namespace Drupal\entity_usage\UrlToEntityIntegrations;

use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Url;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\entity_usage\OptimizedRouteEnhancer;
use Drupal\entity_usage\UrlToEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * Uses the routing system to determine if a URL points to an entity.
 */
class EntityRouting implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'router.no_access_checks')]
    private readonly RequestMatcherInterface&RequestContextAwareInterface $router,
    private readonly UrlToEntityInterface $urlToEntity,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [Events::URL_TO_ENTITY => ['getEntityFromRouting', 1000]];
  }

  /**
   * Gets the entity from a routed URL if possible.
   *
   * @param \Drupal\entity_usage\Events\UrlToEntityEvent $event
   *   The event.
   */
  public function getEntityFromRouting(UrlToEntityEvent $event): void {
    $initial_request_context = $this->router->getContext();
    $request = $event->getRequest();

    try {
      $request->attributes->set(OptimizedRouteEnhancer::ROUTE_ATTRIBUTE, TRUE);
      $this->router->setContext((new RequestContext())->fromRequest($request));
      $attributes = $this->router->matchRequest($request);
    }
    catch (ResourceNotFoundException | ParamNotConvertedException | AccessDeniedHttpException | MethodNotAllowedException | BadRequestException) {
      return;
    } finally {
      $this->router->setContext($initial_request_context);
    }

    if (!$attributes) {
      return;
    }

    $route_name = $attributes[RouteObjectInterface::ROUTE_NAME];
    $route_parameters = $attributes['_raw_variables']->all();

    $url_object = new Url($route_name, $route_parameters, ['query' => $request->query->all()]);
    $entity_info = $this->urlToEntity->findEntityIdByRoutedUrl($url_object);
    if ($entity_info !== NULL) {
      $event->setEntityInfo($entity_info['type'], $entity_info['id']);
    }
  }

}
