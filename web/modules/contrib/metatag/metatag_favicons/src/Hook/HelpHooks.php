<?php

namespace Drupal\metatag_favicons\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_favicons.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the metatag_favicons module.
      case 'help.page.metatag_favicons':
        $output = '';
        $output .= '<h3>' . (string) new TranslatableMarkup('About') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('Provides support for many different favicons.') . '</p>';
        return $output;

      default:
    }
  }

}
