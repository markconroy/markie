<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * The standard page title.
 */
#[MetatagTag(
  id: 'title',
  label: new TranslatableMarkup('Page title'),
  name: 'title',
  group: 'basic',
  type: MetatagType::Label,
  description: new TranslatableMarkup("The text to display in the title bar of a visitor's web browser when they view this page. This meta tag may also be used as the title of the page when a visitor bookmarks or favorites this page, or as the page title in a search engine result. It is common to append '[site:name]' to the end of this, so the site's name is automatically added. It is recommended that the title is no greater than 55 - 65 characters long, including spaces."),
  weight: -1,
  trimmable: TRUE,
)]

class Title extends MetaNameBase {

  /**
   * {@inheritdoc}
   */
  public function getTestOutputExistsXpath(): array {
    return ["//title"];
  }

  /**
   * {@inheritdoc}
   */
  public function getTestOutputValuesXpath(array $values): array {
    // @todo This isn't actually testing the output.
    return ["//title"];
  }

}
