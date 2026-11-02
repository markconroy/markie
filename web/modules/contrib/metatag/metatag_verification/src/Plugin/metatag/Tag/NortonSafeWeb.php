<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'norton-safeweb-site-verification' meta tag.
 */
#[MetatagTag(
  id: 'norton_safe_web',
  label: new TranslatableMarkup('Norton Safe Web'),
  name: 'norton-safeweb-site-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://safeweb.norton.com/',
    '@name' => 'Norton Safe Web',
    ':verify_url' => 'https://safeweb.norton.com/help/site_owners',
  ]),
  weight: 5,
)]
class NortonSafeWeb extends MetaNameBase {
}
