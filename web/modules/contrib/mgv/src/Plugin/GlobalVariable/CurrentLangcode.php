<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\mgv\Plugin\GlobalVariable;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class CurrentLangcode.
 *
 * Print the current langcode. This could be useful if you want to do a
 * "Back to Search" type feature, but need to ensure you keep the current
 * selected language e.g. "/fr/node/123" and "/ga/node/123".
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "current_langcode",
 * );
 */
class CurrentLangcode extends GlobalVariable implements ContainerFactoryPluginInterface {

  /**
   * Language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->languageManager->getCurrentLanguage()->getId();
  }

}
