<?php

namespace Drupal\metatag_facebook\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Facebook "fb:app_id" meta tag.
 */
#[MetatagTag(
  id: 'fb_app_id',
  label: new TranslatableMarkup('Facebook Application ID'),
  name: 'fb:app_id',
  group: 'facebook',
  type: MetatagType::String,
  description: new TranslatableMarkup('A comma-separated list of Facebook Platform Application IDs applicable for this site.'),
  weight: 2,
)]
class FbAppId extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
