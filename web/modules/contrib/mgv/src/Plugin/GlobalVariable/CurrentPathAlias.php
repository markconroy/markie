<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CurrentPathAlias.
 *
 * Print the current path alias. This could be useful if you want to ensure
 * the alias rather than the path is used.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_path_alias",
 * );
 */
class CurrentPathAlias extends CurrentPath {

  /**
   * Path alias manager instance.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $pathAliasManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('path.current'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CurrentPathStack $current_path_stack, AliasManagerInterface $path_alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $current_path_stack);
    $this->pathAliasManager = $path_alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->pathAliasManager->getAliasByPath(parent::getValue());
  }

}
