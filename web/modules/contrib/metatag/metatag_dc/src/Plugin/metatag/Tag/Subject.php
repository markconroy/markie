<?php

namespace Drupal\metatag_dc\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Dublin Core "Subject" meta tag.
 */
#[MetatagTag(
  id: 'dcterms_subject',
  label: new TranslatableMarkup('Subject'),
  name: 'dcterms.subject',
  group: 'dublin_core',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The topic of the resource. Typically, the subject will be represented using keywords, key phrases, or classification codes. Recommended best practice is to use a controlled vocabulary. To describe the spatial or temporal topic of the resource, use the Coverage element.'),
  weight: 3,
)]
class Subject extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
