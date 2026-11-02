<?php

namespace Drupal\metatag_hreflang\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a new hreflang tag plugin for each enabled language.
 */
class HreflangDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a HreflangDeriver instance.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // Get a list of all defined languages.
    $languages = $this->languageManager
      ->getLanguages(LanguageInterface::STATE_ALL);

    // Now we loop over them and declare the derivatives.
    /** @var \Drupal\Core\Language\LanguageInterface $language */
    foreach ($languages as $langcode => $language) {
      // Ignore the global values.
      if ($langcode == Language::LANGCODE_NOT_SPECIFIED) {
        continue;
      }
      elseif ($langcode == Language::LANGCODE_NOT_APPLICABLE) {
        continue;
      }

      // The base definition includes the annotations defined in the plugin,
      // i.e. HreflangPerLanguage. Each one may be overridden.
      $derivative = $base_plugin_definition;

      // Here we fill in any missing keys on the layout annotation.
      $derivative['weight']++;
      $derivative['id'] = 'hreflang_' . $langcode;
      // The 'name' value is used as the value of the 'hreflang' attribute on
      // the HTML tag.
      $derivative['name'] = $langcode;
      $derivative['label'] = $this->t("URL for a version of this page in %langcode", ['%langcode' => $language->getName()]);
      $derivative['description'] = '';

      // Reference derivatives based on their UUID instead of the record ID.
      $this->derivatives[$derivative['id']] = $derivative;
    }

    return $this->derivatives;
  }

}
