<?php

namespace Drupal\Core\Menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheCollector;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides the default implementation of the active menu trail service.
 *
 * It uses the current route name and route parameters to compare with the ones
 * of the menu links.
 */
class MenuActiveTrail extends CacheCollector implements MenuActiveTrailInterface {

  /**
   * The menu link plugin manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * The route match object for the current page.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a \Drupal\Core\Menu\MenuActiveTrail object.
   *
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   The menu link plugin manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   A route match object for finding the active link.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Drupal\Core\Path\PathMatcherInterface|null $pathMatcher
   *   The path.matcher service.
   */
  public function __construct(
    MenuLinkManagerInterface $menu_link_manager,
    RouteMatchInterface $route_match,
    CacheBackendInterface $cache,
    LockBackendInterface $lock,
    protected ?PathMatcherInterface $pathMatcher = NULL,
  ) {
    parent::__construct(NULL, $cache, $lock);
    $this->menuLinkManager = $menu_link_manager;
    $this->routeMatch = $route_match;
    if ($this->pathMatcher === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $pathMatcher argument is deprecated in drupal:11.2.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3523495', E_USER_DEPRECATED);
      $this->pathMatcher = \Drupal::service('path.matcher');
    }
  }

  /**
   * {@inheritdoc}
   *
   * @see ::getActiveTrailIds()
   */
  protected function getCid() {
    if (!isset($this->cid)) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();
      ksort($route_parameters);
      $this->cid = 'active-trail:route:' . $this->routeMatch->getRouteName() . ':route_parameters:' . serialize($route_parameters);
    }

    return $this->cid;
  }

  /**
   * {@inheritdoc}
   *
   * @see ::getActiveTrailIds()
   */
  protected function resolveCacheMiss($menu_name) {
    $this->storage[$menu_name] = $this->doGetActiveTrailIds($menu_name);
    if (empty($menu_name)) {
      // We look in every menu so invalidate when any menu or menu item changes.
      $this->tags[] = 'config:menu_list';
      $this->tags[] = 'menu_link_content_list';
    }
    else {
      $this->tags[] = 'config:system.menu.' . $menu_name;
    }
    $this->persist($menu_name);

    return $this->storage[$menu_name];
  }

  /**
   * {@inheritdoc}
   *
   * This implementation caches all active trail IDs per route match for *all*
   * menus whose active trails are calculated on that page. This ensures 1 cache
   * get for all active trails per page load, rather than N.
   *
   * It uses the cache collector pattern to do this.
   *
   * @see ::get()
   * @see \Drupal\Core\Cache\CacheCollectorInterface
   * @see \Drupal\Core\Cache\CacheCollector
   */
  public function getActiveTrailIds($menu_name) {
    return $this->get($menu_name);
  }

  /**
   * Helper method for ::getActiveTrailIds().
   */
  protected function doGetActiveTrailIds($menu_name) {
    // Parent ids; used both as key and value to ensure uniqueness.
    // We always want all the top-level links with parent == ''.
    $active_trail = ['' => ''];

    // If a link in the given menu indeed matches the route, then use it to
    // complete the active trail.
    if ($active_link = $this->getActiveLink($menu_name)) {
      if ($parents = $this->menuLinkManager->getParentIds($active_link->getPluginId())) {
        $active_trail = $parents + $active_trail;
      }
    }

    return $active_trail;
  }

  /**
   * {@inheritdoc}
   */
  public function getActiveLink($menu_name = NULL) {
    // Note: this is a very simple implementation. If you need more control
    // over the return value, such as matching a prioritized list of menu names,
    // you should substitute your own implementation for the 'menu.active_trail'
    // service in the container.
    // The menu links coming from the storage are already sorted by depth,
    // weight and ID.
    $found = NULL;
    $links = [];

    $route_name = $this->routeMatch->getRouteName();
    // On a default (not custom) 403 page the route name is NULL. On a custom
    // 403 page we will get the route name for that page, so we can consider
    // it a feature that a relevant menu tree may be displayed.
    if ($route_name) {
      $route_parameters = $this->routeMatch->getRawParameters()->all();

      // Load links matching this route.
      $links = $this->menuLinkManager->loadLinksByRoute($route_name, $route_parameters, $menu_name);
    }

    // If the request is for the site's front page, then menu links containing
    // <front> must also be loaded since it's a special route that's an alias
    // for the page.
    // @todo Combine the two calls to loadLinksByRoute() in
    // https://www.drupal.org/project/drupal/issues/3523497
    if ($this->pathMatcher->isFrontPage()) {
      $links = array_merge($links, $this->menuLinkManager->loadLinksByRoute('<front>', [], $menu_name));
    }

    // Select the first matching link.
    if ($links) {
      $found = reset($links);
    }
    return $found;
  }

}
