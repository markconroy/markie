<?php

namespace Drupal\menu_trail_by_path\Path;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

class CurrentPathHelper implements PathHelperInterface {
  /**
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\Core\Routing\RequestContext
   */
  private $context;

  public function __construct(RouteMatchInterface $route_match, RequestContext $context) {
    $this->routeMatch = $route_match;
    $this->context    = $context;
  }

  /**
   * @inheritdoc
   */
  public function getUrls() {
    $trail_urls = $this->getCurrentPathUrls();
    if ($current_request_url = $this->getCurrentRequestUrl()) {
      $trail_urls[] = $current_request_url;
    }

    return $trail_urls;
  }

  /**
   * Returns the current request Url
   *
   * NOTE: There is a difference between $this->routeMatch->getRouteName and $this->context->getPathInfo()
   * for now it seems more logical to prefer the latter, because that's the "real" url that visitors enter in their browser..
   *
   * @return \Drupal\Core\Url|null
   */
  protected function getCurrentRequestUrl() {
    $current_pathinfo_url = $this->createUrlFromRelativeUri($this->context->getPathInfo());
    if ($current_pathinfo_url->isRouted()) {
      return $current_pathinfo_url;
    }
    elseif ($route_name = $this->routeMatch->getRouteName()) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      return new Url($route_name, $route_parameters);
    }

    return NULL;
  }

  /**
   * @return \Drupal\Core\Url[]
   */
  protected function getCurrentPathUrls() {
    $urls = [];

    $path = trim($this->context->getPathInfo(), '/');
    $path_elements = explode('/', $path);

    while (count($path_elements) > 1) {
      array_pop($path_elements);
      $url = $this->createUrlFromRelativeUri('/' . implode('/', $path_elements));
      if ($url->isRouted()) {
        $urls[] = $url;
      }
    }

    return array_reverse($urls);
  }

  /**
   * Create a Url Object from a relative uri (e.g. /news/drupal8-release-party)
   *
   * @param $relativeUri
   * @return Url
   */
  protected function createUrlFromRelativeUri($relativeUri) {
    // @see https://www.drupal.org/node/2810961
    if (UrlHelper::isExternal(substr($relativeUri, 1))) {
      return Url::fromUri('base:' . $relativeUri);
    }

    return Url::fromUserInput($relativeUri);
  }
}
