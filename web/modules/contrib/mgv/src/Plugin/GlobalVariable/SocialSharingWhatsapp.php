<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Url;
use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SocialSharingWhatsapp.
 *
 * Social Sharing Global Variables
 * To use this, you need to wrap the variable in an anchor tag, such as:
 * ```
 * <a href="{{ global_variables.social_sharing.whatsapp }}">Whatsapp</a>
 * ```
 * Share the current page on Whatsapp.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "social_sharing\whatsapp",
 *   variableDependencies={
 *     "current_page_title",
 *     "current_path",
 *     "base_url",
 *   }
 * );
 */
class SocialSharingWhatsapp extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return Url::fromUri(
      'whatsapp://send',
      [
        'absolute' => TRUE,
        'query' => [
          'text' => $this->getDependency('current_page_title') . ' - ' . $this->getDependency('base_url') . $this->getDependency('current_path'),
        ],
      ]
    )
      ->toUriString();
  }

}
