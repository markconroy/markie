<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SocialSharingTwitter.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "social_sharing\twitter",
 *   variableDependencies={
 *     "current_page_title",
 *     "current_path",
 *     "base_url",
 *   }
 * );
 */
class SocialSharingTwitter extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Social Sharing Global Variables
    //
    // To use this, you need to wrap the variable in an anchor tag, such as:
    // <a href="{{ global_variables.social_sharing.twitter }}">Twitter</a>
    //
    // Share the current page on Twitter.
    return Url::fromUri(
      'https://twitter.com/share',
      [
        'absolute' => TRUE,
        'https' => TRUE,
        'query' => [
          'url' => $this->getDependency('base_url') . $this->getDependency('current_path'),
          'text' => $this->getDependency('current_page_title'),
        ],
      ])
      ->toUriString();
  }

}
