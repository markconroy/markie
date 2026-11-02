<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * The Favicons "apple-touch-icon" meta tag.
 */
#[MetatagTag(
  id: 'apple_touch_icon',
  label: new TranslatableMarkup('Apple touch icon: 60px x 60px'),
  name: 'apple-touch-icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A PNG image that is 60px wide by 60px high. Used with the non-Retina iPhone, iPod Touch, and Android 2.1+ devices.'),
  weight: 7,
)]
class AppleTouchIcon extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    // This is the one "apple touch" icon that doesn't have a size attribute.
    return ["//link[@rel='{$this->name}' and not(@sizes)]"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='{$this->name}' and not(@sizes) and @" . $this->htmlValueAttribute . "='{$value}']";
    }
    return $xpath_strings;
  }

}
