<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * The Favicons "shortcut icon" meta tag.
 */
#[MetatagTag(
  id: 'shortcut_icon',
  label: new TranslatableMarkup('Default icon'),
  name: 'icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('The traditional favicon, must be either a GIF, ICO, JPG/JPEG or PNG image.'),
  weight: 1,
)]
class ShortcutIcon extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    // This is the one icon tag that doesn't have a size attribute.
    return ["//link[@rel='{$this->name}' and not(@sizes) and not(@type)]"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='{$this->name}' and not(@sizes) and not(@type) and @" . $this->htmlValueAttribute . "='{$value}']";
    }
    return $xpath_strings;
  }

}
