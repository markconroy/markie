<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'yandex-verification' meta tag.
 */
#[MetatagTag(
  id: 'yandex',
  label: new TranslatableMarkup('Yandex'),
  name: 'yandex-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.', [
    ':url'  => 'https://www.yandex.com/',
    '@name' => 'Yandex',
    ':verify_url' => 'https://webmaster.yandex.com/',
  ]),
  weight: 8,
)]
class Yandex extends MetaNameBase {
}
