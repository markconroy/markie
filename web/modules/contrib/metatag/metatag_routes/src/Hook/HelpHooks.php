<?php

namespace Drupal\metatag_routes\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Hook implementations for metatag_routes.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the ok_metatag_custom module.
      case 'help.page.metatag_routes':
        $output = '';
        $output .= '<h3>' . (string) new TranslatableMarkup('About') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('Enables metatags for custom routes') . '</p>';
        return $output;

      default:
    }
  }

}
