<?php

namespace Drupal\metatag_hreflang\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_hreflang.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the metatag_hreflang module.
      case 'help.page.metatag_hreflang':
        $output = '';
        $output .= '<h3>' . (string) new TranslatableMarkup('About') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('Provides support for the hreflang meta tag with some extra logic to simplify it.') . '</p>';
        return $output;

      default:
    }
  }

}
