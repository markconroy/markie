<?php

namespace Drupal\highlight_js\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for highlight_js.
 */
class HighlightJsHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public static function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      case 'help.page.highlight_js':
        $text = file_get_contents(__DIR__ . '/README.txt');
        if (!\Drupal::moduleHandler()->moduleExists('markdown')) {
          return '<pre>' . Html::escape($text) . '</pre>';
        }
        else {
          // Use the Markdown filter to render the README.
          $filter_manager = \Drupal::service('plugin.manager.filter');
          $settings = \Drupal::configFactory()->get('markdown.settings')->getRawData();
          $config = [
            'settings' => $settings,
          ];
          $filter = $filter_manager->createInstance('markdown', $config);
          return $filter->process($text, 'en');
        }
    }
    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public static function theme($existing, $type, $theme, $path) {
    return [
      'highlight_js_language_select' => [
        'variables' => [
          'text' => NULL,
          'url' => NULL,
          'language' => NULL,
        ],
        'template' => 'highlight-js/highlight_js-language-select',
      ],
    ];
  }

}
