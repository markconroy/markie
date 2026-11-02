<?php

namespace Drupal\metatag_open_graph_products\Hook;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_open_graph_products.
 */
class HelpHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Main module help for the metatag_open_graph_products module.
      case 'help.page.metatag_open_graph_products':
        $output = '';
        $output .= '<h3>' . (string) new TranslatableMarkup('About') . '</h3>';
        $output .= '<p>' . (string) new TranslatableMarkup('Provides additional Open Graph Protocol meta tags for describing products.') . '</p>';
        return $output;

      default:
    }
  }

}
