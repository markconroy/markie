<?php

namespace Drupal\mgv\Plugin\GlobalVariable;

use Drupal\Core\Url;
use Drupal\mgv\Plugin\GlobalVariable;

/**
 * Class SocialSharingEmail.
 *
 * @package Drupal\mgv\Plugin\GlobalVariable
 *
 * @Mgv(
 *   id = "social_sharing\email",
 *   variableDependencies={
 *     "current_page_title",
 *     "site_name",
 *     "base_url",
 *     "current_path",
 *   }
 * );
 */
class SocialSharingEmail extends GlobalVariable {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    // Social Sharing Global Variables
    //
    // To use this, you need to wrap the variable in an anchor tag, such as:
    // <a href="{{ global_variables.social_sharing.email }}">Email</a>
    //
    // Share the current page by Email.
    return Url::fromUri(
      'mailto:',
      [
        'query' => [
          'subject' => $this->getDependency('current_page_title'),
          'body' => $this->t(
            'Check this out from @sitename: :base_url:current_path',
            [
              '@sitename' => $this->getDependency('site_name'),
              ':base_url' => $this->getDependency('base_url'),
              ':current_path' => $this->getDependency('current_path'),
            ]
          ),
        ],
      ])
      ->toUriString();
  }

}
