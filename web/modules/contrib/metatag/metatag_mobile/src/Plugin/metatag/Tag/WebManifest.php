<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * The Web Manifest for Progressive Web Apps.
 */
#[MetatagTag(
  id: 'web_manifest',
  label: new TranslatableMarkup('Web Manifest'),
  name: 'manifest',
  group: 'mobile',
  type: MetatagType::Uri,
  description: new TranslatableMarkup(
    'A URL to a manifest.json file that describes the application. The <a href=":url">JSON-based manifest</a> provides developers with a centralized place to put metadata associated with a web application.',
    [':url' => 'https://developer.mozilla.org/en-US/docs/Web/Manifest'],
  ),
  weight: 92,
)]
class WebManifest extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    // Get the standard LinkRelBase output.
    $element = parent::output();

    // This attribute is required on the tag to avoid errors in Chrome.
    // @see https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/crossorigin
    // @see https://developer.chrome.com/docs/extensions/mv2/xhr/
    if (!empty($element) && is_array($element)) {
      $element['#attributes']['crossorigin'] = 'use-credentials';
    }

    return $element;
  }

}
