<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'msvalidate.01' meta tag.
 */
#[MetatagTag(
  id: 'bing',
  label: new TranslatableMarkup('Bing'),
  name: 'msvalidate.01',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://www.bing.com/',
    '@name' => 'Bing',
    ':verify_url' => 'https://www.bing.com/webmaster/help/how-to-verify-ownership-of-your-site-afcfefc6',
  ]),
  weight: 2,
)]
class Bing extends MetaNameBase {
}
