<?php

namespace Drupal\metatag_google_cse\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'doc_status' meta tag.
 */
#[MetatagTag(
  id: 'doc_status',
  label: new TranslatableMarkup('Document status'),
  name: 'doc_status',
  group: 'google_cse',
  type: MetatagType::String,
  description: new TranslatableMarkup('The document status, e.g. "draft".'),
  weight: 0,
)]
class DocStatus extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
