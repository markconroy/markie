<?php

namespace Drupal\metatag_dc\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The DublinCore group.
 */
#[MetatagGroup(
  id: 'dublin_core',
  label: new TranslatableMarkup('Dublin Core'),
  description: new TranslatableMarkup('Provides the fifteen <a href=":docs">Dublin Core Metadata Element Set 1.1</a> meta tags from the <a href=":link">Dublin Core Metadata Institute</a>', [
    ":docs" => "https://dublincore.org/documents/dces/",
    ":link" => "https://dublincore.org/",
  ]),
  weight: 4,
)]
class DublinCore extends GroupBase {
  // Inherits everything from Base.
}
