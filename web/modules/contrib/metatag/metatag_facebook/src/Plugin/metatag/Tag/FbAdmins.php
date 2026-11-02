<?php

namespace Drupal\metatag_facebook\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The Facebook "fb:admins" meta tag.
 */
#[MetatagTag(
  id: 'fb_admins',
  label: new TranslatableMarkup('Facebook Admins'),
  name: 'fb:admins',
  group: 'facebook',
  type: MetatagType::String,
  description: new TranslatableMarkup('A comma-separated list of Facebook user IDs of people who are considered administrators or moderators of this page.'),
  weight: 1,
)]
class FbAdmins extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
