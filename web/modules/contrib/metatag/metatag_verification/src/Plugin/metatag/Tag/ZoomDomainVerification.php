<?php

namespace Drupal\metatag_verification\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * Provides a plugin for the 'zoom-domain-verification' meta tag.
 */
#[MetatagTag(
  id: 'zoom_domain_verification',
  label: new TranslatableMarkup('Zoom'),
  name: 'zoom-domain-verification',
  group: 'site_verification',
  type: MetatagType::Label,
  description: new TranslatableMarkup(
    'A string provided by <a href=":url">@name</a>, full details are available from the <a href=":verify_url">@name online help</a>.',
    [
      ':url'  => 'https://zoom.us/',
      '@name' => 'Zoom',
      ':verify_url' => 'https://support.zoom.us/hc/en-us/articles/203395207-What-is-Managed-Domain',
    ],
  ),
  weight: 9,
)]
class ZoomDomainVerification extends MetaNameBase {
}
