<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'facebook-domain-verification' meta tag.
 */
#[MetatagTag(
  id: 'facebook_domain_verification',
  label: new TranslatableMarkup('Facebook'),
  name: 'facebook-domain-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://www.facebook.com/',
    '@name' => 'Facebook',
    ':verify_url' => 'https://developers.facebook.com/docs/sharing/domain-verification/verifying-your-domain/#meta-tags',
  ]),
  weight: 3,
)]
class FacebookDomainVerification extends MetaNameBase {
}
