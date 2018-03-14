<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SocialSharingLinkedin.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "social_sharing\linkedin",
 *   variableDependencies={
 *     "current_page_title",
 *     "site_name",
 *     "current_path",
 *     "base_url",
 *   }
 * );
 */
class SocialSharingLinkedin extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Social Sharing Global Variables
    //
    // To use this, you need to wrap the variable in an anchor tag, such as:
    // <a href="{{ global_variables.social_sharing.linkedin }}">Linkedin</a>
    //
    // Share the current page on Twitter.
    return Url::fromUri(
      'https://www.linkedin.com/shareArticle',
      [
        'absolute' => TRUE,
        'https' => TRUE,
        'query' => [
          'mini' => 'true',
          'url' => $this->getDependency('base_url') . $this->getDependency('current_path'),
          'title' => $this->getDependency('current_page_title'),
          'source' => $this->getDependency('site_name'),
        ],
      ])
      ->toUriString();
  }

}
