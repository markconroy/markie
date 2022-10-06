<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Url;
use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SocialSharingFacebook.
 *
 * Social Sharing Global Variables
 * To use this, you need to wrap the variable in an anchor tag, such as:
 * ```
 * <a href="{{ global_variables.social_sharing.facebook }}">Facebook</a>
 * ```
 * Share the current page on Facebook.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "social_sharing\facebook",
 *   variableDependencies={
 *     "current_page_title",
 *     "current_path",
 *     "base_url",
 *   }
 * );
 */
class SocialSharingFacebook extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return Url::fromUri(
      'https://www.facebook.com/sharer.php',
      [
        'absolute' => TRUE,
        'https' => TRUE,
        'query' => [
          'u' => $this->getDependency('base_url') . $this->getDependency('current_path'),
          'text' => $this->getDependency('current_page_title'),
        ],
      ])
      ->toUriString();
  }

}
