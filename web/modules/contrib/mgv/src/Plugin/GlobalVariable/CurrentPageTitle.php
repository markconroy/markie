<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Controller\TitleResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CurrentPageTitle.
 *
 * Print the current page's title. This could be useful if you want to add
 * the current page title as a breadcrumb.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_page_title",
 * );
 */
class CurrentPageTitle extends RawCurrentPageTitle {

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('title_resolver'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $requestStack, TitleResolverInterface $title_resolver, RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $requestStack, $title_resolver);
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $value = parent::getValue();
    if (!empty($value)) {
      if (is_array($value)) {
        $value = $this->renderer->render($value);
      }
      elseif (is_object($value)) {
        $value = (string) $value;
      }
    }
    return $value;
  }

}
