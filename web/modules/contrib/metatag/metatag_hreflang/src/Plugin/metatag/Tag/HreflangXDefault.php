<?php

namespace Drupal\metatag_hreflang\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'hreflang_xdefault' meta tag.
 */
#[MetatagTag(
  id: 'hreflang_xdefault',
  label: new TranslatableMarkup('Default locale (x-default)'),
  name: 'hreflang_xdefault',
  group: 'hreflang',
  type: MetatagType::Uri,
  description: new TranslatableMarkup('This should point to the version of the page that is for the main or primary locale, e.g. the original version of an article that is translated into other languages.'),
  weight: 0,
)]
class HreflangXDefault extends HreflangBase {

  /**
   * {@inheritdoc}
   */
  public function name(): string {
    return 'x-default';
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    return ["//link[@rel='alternate' and @hreflang='x-default']"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    $xpath_strings = [];
    foreach ($values as $value) {
      $xpath_strings[] = "//link[@rel='alternate' and @hreflang='x-default' and @href='{$value}']";
    }
    return $xpath_strings;
  }

}
