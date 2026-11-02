<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Language" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_language',
  label: new TranslatableMarkup('Language'),
  name: 'dcterms.language',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A language of the resource. Recommended best practice is to use a controlled vocabulary such as RFC 4646 [RFC4646].'),
  weight: 12,
)]
class Language extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
