<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'google-site-verification' meta tag.
 */
#[MetatagTag(
  id: 'google_site_verification',
  label: new TranslatableMarkup('Google Site Verification'),
  name: 'google-site-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://www.google.com/',
    '@name' => 'Google',
    ':verify_url' => 'https://support.google.com/webmasters/answer/35179?hl=en',
  ]),
  weight: 4,
  multiple: TRUE,
)]
class Google extends MetaNameBase {
}
