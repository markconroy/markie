<?php

namespace Drupal\metatag_dc_advanced\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_dc_advanced.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the metatag_dc_advanced module.
      case 'help.page.metatag_dc_advanced':
        $output = '';
        $output .= '<h3>' . (string) new TranslatableMarkup('About') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('Provides forty additional meta tags from the <a href="https://dublincore.org/">Dublin Core Metadata Institute</a>.') . '</p>';
        return $output;

      default:
    }
  }

}
