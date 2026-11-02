<?php

namespace Drupal\metatag_open_graph_products\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * Provides a plugin for the 'Open Graph - Products' meta tag group.
 */
#[MetatagGroup(
  id: 'open_graph_products',
  label: new TranslatableMarkup('Open Graph - Products'),
  description: new TranslatableMarkup(
    'These <a href=":url">Open Graph meta tags</a> are for describing products.<br><br>The Facebook <a href=":sharing_debugger">Sharing Debugger</a> lets you preview how your content will look when it\'s shared to Facebook and debug any issues with your Open Graph tags.',
    [
      ':url' => 'https://ogp.me/',
      ':sharing_debugger' => 'https://developers.facebook.com/tools/debug/',
    ]
  ),
  weight: 0,
)]
class OpenGraphProducts extends GroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
