<?php

namespace Drupal\metatag_app_links\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * The AppLinks Web Should Fallback meta tag.
 */
#[MetatagTag(
  id: 'al_web_should_fallback',
  label: new TranslatableMarkup('Should fallback'),
  name: 'al:web:should_fallback',
  group: 'app_links',
  type: MetatagType::String,
  description: new TranslatableMarkup('Indicates if the web URL should be used as a fallback; defaults to "true".'),
  weight: 2,
)]
class AlWebShouldFallback extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
