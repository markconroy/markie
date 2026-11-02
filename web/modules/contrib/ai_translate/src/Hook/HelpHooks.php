<?php

declare(strict_types=1);

namespace Drupal\ai_translate\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Help hook implementations for ai_translate.
 */
class HelpHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name !== 'help.page.ai_translate') {
      return NULL;
    }
    $output = '<h3>' . $this->t('About') . '</h3>';
    $output .= '<p>' . $this->t('This module will integrate AI with our Drupal system. It will add a Translate link to translate an entire piece of content into another language in one click.');
    $output .= ' ' . $this->t('For more information, see the <a href=":drupal-org-help">online documentation for the ai_translate module</a>.', [':drupal-org-help' => 'https://www.drupal.org/project/ai_translate']);
    $output .= '</p>';
    return $output;
  }

}
