<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'p:domain_verify' meta tag.
 */
#[MetatagTag(
  id: 'pinterest',
  label: new TranslatableMarkup('Pinterest'),
  name: 'p:domain_verify',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://www.pinterest.com/',
    '@name' => 'Pinterest',
    ':verify_url' => 'https://help.pinterest.com/en/business/article/claim-your-website',
  ]),
  weight: 6,
)]
class Pinterest extends MetaNameBase {
}
