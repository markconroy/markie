<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Web URL meta tag.
 */
#[MetatagTag(
  id: 'al_web_url',
  label: new TranslatableMarkup('Web URL'),
  name: 'al:web:url',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('The web URL; defaults to the URL for the content that contains this tag.'),
  weight: 2,
)]
class AlWebUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
