<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'og:video:secure_url' meta tag.
 */
#[MetatagTag(
  id: 'og_video_secure_url',
  label: new TranslatableMarkup('Video Secure URL'),
  name: 'og:video:secure_url',
  group: 'open_graph',
  type: MetatagType::Video,
  description: new TranslatableMarkup('The secure URL (HTTPS) of a video which should represent the content.'),
  weight: 11,
  secure: TRUE,
  absolute_url: TRUE,
)]
class OgVideoSecureUrl extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
