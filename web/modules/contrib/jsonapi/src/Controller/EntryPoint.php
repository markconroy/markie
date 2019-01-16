<?php

namespace Drupal\jsonapi\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\JsonApiResource\EntityCollection;
use Drupal\jsonapi\JsonApiResource\JsonApiDocumentTopLevel;
use Drupal\jsonapi\JsonApiResource\NullEntityCollection;
use Drupal\jsonapi\ResourceResponse;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Controller for the API entry point.
 *
 * @internal
 */
class EntryPoint extends ControllerBase {

  /**
   * The JSON:API resource type repository.
   *
   * @var \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface
   */
  protected $resourceTypeRepository;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The account object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * EntryPoint constructor.
   *
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepositoryInterface $resource_type_repository
   *   The resource type repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   */
  public function __construct(ResourceTypeRepositoryInterface $resource_type_repository, RendererInterface $renderer, AccountInterface $user) {
    $this->resourceTypeRepository = $resource_type_repository;
    $this->renderer = $renderer;
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('jsonapi.resource_type.repository'),
      $container->get('renderer'),
      $container->get('current_user')
    );
  }

  /**
   * Controller to list all the resources.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The response object.
   */
  public function index() {
    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['user.roles:authenticated'])
      ->addCacheTags(['jsonapi_resource_types']);

    // Execute the request in context so the cacheable metadata from the entity
    // grants system is caught and added to the response. This is surfaced when
    // executing the underlying entity query.
    $context = new RenderContext();
    /** @var \Drupal\Core\Cache\CacheableResponseInterface $response */
    $do_build_urls = function () {
      $self = Url::fromRoute('jsonapi.resource_list')->setAbsolute();

      // Only build URLs for exposed resources.
      $resources = array_filter($this->resourceTypeRepository->all(), function ($resource) {
        return !$resource->isInternal();
      });

      return array_reduce($resources, function (array $carry, ResourceType $resource_type) {
        if ($resource_type->isLocatable() || $resource_type->isMutable()) {
          $route_suffix = $resource_type->isLocatable() ? 'collection' : 'collection.post';
          $url = Url::fromRoute(sprintf('jsonapi.%s.%s', $resource_type->getTypeName(), $route_suffix))->setAbsolute();
          $carry[$resource_type->getTypeName()] = ['href' => $url->toString()];
        }
        return $carry;
      }, ['self' => ['href' => $self->toString()]]);
    };
    $urls = $this->renderer->executeInRenderContext($context, $do_build_urls);
    if (!$context->isEmpty()) {
      $cacheability = $cacheability->merge($context->pop());
    }

    $meta = [];
    if ($this->user->isAuthenticated()) {
      $current_user_uuid = User::load($this->user->id())->uuid();
      $meta['links']['me'] = ['meta' => ['id' => $current_user_uuid]];
      $cacheability->addCacheContexts(['user']);
      try {
        $me_url = Url::fromRoute(
          'jsonapi.user--user.individual',
          ['entity' => $current_user_uuid]
        )
          ->setAbsolute()
          ->toString(TRUE);
        $meta['links']['me']['href'] = $me_url->getGeneratedUrl();
        // The cacheability of the `me` URL is the cacheability of that URL
        // itself and the currently authenticated user.
        $cacheability = $cacheability->merge($me_url);
      }
      catch (RouteNotFoundException $e) {
        // Do not add the link if the route is disabled or marked as internal.
      }
    }

    $response = new ResourceResponse(new JsonApiDocumentTopLevel(new EntityCollection([]), new NullEntityCollection(), $urls, $meta));
    return $response->addCacheableDependency($cacheability);
  }

}
