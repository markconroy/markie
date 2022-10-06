<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Controller\TitleResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class RawCurrentPageTitle.
 *
 * Print the current page's title. This could be useful if you want to add
 * the current page title as a breadcrumb.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "raw_current_page_title",
 * );
 */
class RawCurrentPageTitle extends GlobalVariable implements ContainerFactoryPluginInterface {

  /**
   * Current request instance.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected $request;

  /**
   * Title resolver.
   *
   * @var \Drupal\Core\Controller\TitleResolverInterface
   */
  protected $titleResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('title_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, TitleResolverInterface $title_resolver) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->request = $requestStack->getCurrentRequest();
    $this->titleResolver = $title_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Print the current page's title. This could be useful if you want to add
    // the current page title as a breadcrumb.
    if ($route = $this->request->attributes->get('_route_object')) {
      $title = $this->titleResolver->getTitle($this->request, $route);
    }
    return empty($title) ? '' : $title;
  }

}
