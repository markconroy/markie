<?php

namespace Drupal\entity_usage;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Url;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a service to determine if a URL references an entity.
 *
 * Subscribers to the Events::URL_TO_ENTITY event can use different methods for
 * retrieving entity information from a URL string, such as entity routing,
 * mapping to files, checking redirects, etc. The first subscriber that is able
 * to identify an entity from the URL is expected to use
 * UrlToEntityEvent::setEntityInfo() to store the entity information in the
 * event object, which will also stop its propagation to further subscribers.
 *
 * @see \Drupal\entity_usage\Events\Events::URL_TO_ENTITY
 * @see \Drupal\entity_usage\Events\UrlToEntityEvent
 */
class UrlToEntity implements UrlToEntityInterface {

  /**
   * The list of domains information considered to be part of the site.
   *
   * @var array<string, array{host_pattern:string, sub_directory:string|false}>
   */
  private array $siteDomains = [];

  /**
   * The list of enabled entity types.
   *
   * @var string[]|null
   */
  private ?array $enabledTargetEntityTypes;

  public function __construct(private readonly InboundPathProcessorInterface $pathProcessor, ConfigFactoryInterface $configFactory, private readonly EventDispatcherInterface $eventDispatcher) {
    $config = $configFactory->get('entity_usage.settings');

    // Convert site domains into a regex pattern.
    foreach ($config->get('site_domains') ?: [] as $site_domain) {
      // Ensure the site domain ends with a single /.
      $site_domain = rtrim($site_domain, '/') . '/';
      $this->siteDomains[$site_domain]['host_pattern'] = '/' . preg_quote($site_domain, '/') . '/';
      if (preg_match('#^[^/]+(/.+)#', $site_domain, $matches)) {
        $this->siteDomains[$site_domain]['sub_directory'] = $matches[1];
      }
      else {
        $this->siteDomains[$site_domain]['sub_directory'] = FALSE;
      }
    }

    $this->enabledTargetEntityTypes = $config->get('track_enabled_target_entity_types');
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIdByUrl(string $url): ?array {
    if (empty($url)) {
      return NULL;
    }

    $url = $this->makeUrlRelative($url);
    if ($url === NULL) {
      return NULL;
    }
    $url = ltrim($url, '/');
    $parsed_url = UrlHelper::parse($url);
    if ($parsed_url['path'] == '<front>' || $parsed_url['path'] == '<none>') {
      return NULL;
    }

    // If passed an invalid or malformed URL exit early without triggering an
    // exception.
    try {
      $request = Request::create('/' . $url);
    }
    catch (BadRequestException) {
      return NULL;
    }

    $path_processed_url = $this->pathProcessor->processInbound('/' . $url, $request);
    $event = new UrlToEntityEvent($request, $path_processed_url, $this->enabledTargetEntityTypes);
    $this->eventDispatcher->dispatch($event, Events::URL_TO_ENTITY);
    return $event->getEntityInfo();
  }

  /**
   * {@inheritdoc}
   */
  public function findEntityIdByRoutedUrl(Url $url): ?array {
    if (!$url->isRouted()) {
      return NULL;
    }

    if (preg_match(static::ENTITY_ROUTE_PATTERN, $url->getRouteName(), $matches)) {
      $entity_type_id = $matches[1];
      if ($this->isEntityTypeTracked($entity_type_id) && isset($url->getRouteParameters()[$entity_type_id])) {
        return [
          'type' => $entity_type_id,
          'id' => $url->getRouteParameters()[$entity_type_id],
        ];
      }
    }

    return NULL;
  }

  /**
   * Removes the domain from the url if it is considered to be part of the site.
   *
   * @param string $url
   *   A relative or absolute URL string.
   *
   * @return string|null
   *   A relative URL string or NULL if the url is not considered to be part of
   *   the site.
   */
  private function makeUrlRelative(string $url): ?string {
    // Strip off the scheme and host, so we only get the path.
    foreach ($this->siteDomains as $site_domain_info) {
      if (preg_match($site_domain_info['host_pattern'], $url)) {
        // Strip off everything that is not the internal path.
        $url = parse_url($url, PHP_URL_PATH);
        if ($site_domain_info['sub_directory'] !== FALSE && str_starts_with($url, $site_domain_info['sub_directory'])) {
          $url = substr($url, strlen($site_domain_info['sub_directory']));
        }
        break;
      }
    }
    if (UrlHelper::isExternal($url) && UrlHelper::isValid($url)) {
      return NULL;
    }
    return $url;
  }

  /**
   * Determines if an entity type is tracked.
   *
   * @param string $entity_type_id
   *   The entity type ID to check.
   *
   * @return bool
   *   Determines if an entity type is tracked.
   */
  protected function isEntityTypeTracked(string $entity_type_id): bool {
    // Every entity type is tracked if not set.
    return $this->enabledTargetEntityTypes === NULL || in_array($entity_type_id, $this->enabledTargetEntityTypes, TRUE);
  }

}
