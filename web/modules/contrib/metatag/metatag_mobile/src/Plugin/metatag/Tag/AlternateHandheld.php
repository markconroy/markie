<?php

namespace Drupal\metatag_mobile\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The alternate_handheld meta tag.
 */
#[MetatagTag(
  id: 'alternate_handheld',
  label: new TranslatableMarkup('Handheld URL'),
  name: 'alternate',
  group: 'mobile',
  type: MetatagType::Uri,
  description: new TranslatableMarkup(
    "Provides an absolute URL to a specially formatted version of the current page designed for 'feature phones', mobile phones that do not support modern browser standards. See the <a href=\":url\">official Google Mobile SEO Guide</a> for details on how the page should be formatted.",
    [':url' => 'https://developers.google.com/webmasters/mobile-sites/mobile-seo/other-devices?hl=en#feature_phones'],
  ),
  weight: 8,
)]
class AlternateHandheld extends LinkMediaBase {

  /**
   * {@inheritdoc}
   */
  protected function media(): string {
    return 'handheld';
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    return ["//link[@rel='alternate' and @media='handheld']"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='alternate' and @media='handheld' and @href='{$value}']";
    }
    return $xpath_strings;
  }

}
