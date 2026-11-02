<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;
use Drupal\metatag\Plugin\metatag\Tag\MetaPropertyBase;

/**
 * Provides a plugin for the 'video:actor:role' meta tag.
 */
#[MetatagTag(
  id: 'video_actor_role',
  label: new TranslatableMarkup("Actor's role"),
  name: 'video:actor:role',
  group: 'open_graph',
  type: MetatagType::String,
  description: new TranslatableMarkup('The roles of the actor(s).'),
  weight: 46,
  multiple: TRUE,
)]
class VideoActorRole extends MetaPropertyBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
