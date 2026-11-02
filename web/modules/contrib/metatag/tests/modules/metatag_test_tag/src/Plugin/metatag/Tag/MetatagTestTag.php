<?php

namespace Drupal\metatag_test_tag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * A metatag tag for testing.
 */
#[MetatagTag(
  id: 'metatag_test_tag',
  label: new TranslatableMarkup('Metatag Test'),
  name: 'metatag_test_tag',
  group: 'basic',
  type: MetatagType::Label,
  description: new TranslatableMarkup('A metatag tag for testing.'),
  weight: 3,
)]
class MetatagTestTag extends MetaNameBase {
}
