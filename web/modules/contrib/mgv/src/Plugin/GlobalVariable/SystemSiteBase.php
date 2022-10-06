<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SiteMail.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 */
abstract class SystemSiteBase extends GlobalVariable implements ContainerFactoryPluginInterface {

  /**
   * Config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->config = $config_factory->get('system.site');
  }

}
