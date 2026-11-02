<?php

namespace Drupal\metatag_favicons\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\LinkRelBase;

/**
 * The SVG Favicon meta tag.
 */
#[MetatagTag(
  id: 'svg_icon',
  label: new TranslatableMarkup('SVG icon'),
  name: 'icon',
  group: 'favicons',
  type: MetatagType::Image,
  description: new TranslatableMarkup('A scalable vector graphic (SVG) file.'),
  weight: 2,
)]
class SvgIcon extends LinkRelBase {

  /**
   * {@inheritdoc}
   */
  public function output(): array {
    $element = parent::output();
    if ($element) {
      $element['#attributes']['type'] = 'image/svg+xml';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    return ["//link[@rel='{$this->name}' and @type='image/svg+xml']"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='{$this->name}' and @type='image/svg+xml' and @" . $this->htmlValueAttribute . "='{$value}']";
    }
    return $xpath_strings;
  }

}
