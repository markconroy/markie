<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SiteLogo.
 *
 * Print the Site logo's URL. - we are only printing the URL so you can add
 * custom alt (and other) attributes to the image if you wish.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "logo",
 * );
 */
class SiteLogo extends GlobalVariable implements ContainerFactoryPluginInterface {

  /**
   * Language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $themeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('theme.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ThemeManagerInterface $theme_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $theme_name = $this->themeManager->getActiveTheme()->getName();
    return theme_get_setting('logo.url', $theme_name);
  }

}
