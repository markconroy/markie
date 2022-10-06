<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CurrentPath.
 *
 * Print the current path. This could be useful if you want to do a redirect
 * after a form is submitted, e.g. ?destination={{ current_path }}.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_path",
 * );
 */
class CurrentPath extends GlobalVariable implements ContainerFactoryPluginInterface {

  /**
   * Language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $currentPathStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentPathStack $current_path_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentPathStack = $current_path_stack;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->currentPathStack->getPath();
  }

}
