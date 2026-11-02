<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaNameBase;

/**
 * The Open Graph "Fediverse:creator" meta tag.
 */
#[MetatagTag(
  id: 'fediverse_creator',
  label: new TranslatableMarkup("Creator's fediverse account"),
  name: 'fediverse:creator',
  group: 'open_graph',
  type: MetatagType::Label,
  description: new TranslatableMarkup('The fediverse @username@server for the content creator/author for this page, including the first @ symbol.'),
  weight: 28,
  multiple: TRUE,
)]
class FediverseCreator extends MetaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
