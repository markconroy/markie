<?php

namespace Drupal\ban\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for ban.
 */
class BanHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match): ?string {
    switch ($route_name) {
      case 'help.page.ban':
        $output = '';
        $output .= '<h2>' . $this->t('About') . '</h2>';
        $output .= '<p>' . $this->t('The Ban module allows administrators to ban visits to their site from individual IP addresses. For more information, see the <a href=":url">online documentation for the Ban module</a>.', [':url' => 'https://www.drupal.org/documentation/modules/ban']) . '</p>';
        $output .= '<h2>' . $this->t('Uses') . '</h2>';
        $output .= '<dl>';
        $output .= '<dt>' . $this->t('Banning IP addresses') . '</dt>';
        $output .= '<dd>' . $this->t('Administrators can enter IP addresses to ban on the <a href=":bans">IP address bans</a> page.', [':bans' => Url::fromRoute('ban.admin_page')->toString()]) . '</dd>';
        $output .= '</dl>';
        return $output;

      case 'ban.admin_page':
        return '<p>' . $this->t('IP addresses listed here are banned from your site. Banned addresses are completely forbidden from accessing the site and instead see a brief message explaining the situation.') . '</p>';
    }
    return NULL;
  }

}
