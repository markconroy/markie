<?php

namespace Drupal\metatag_open_graph\Plugin\metatag\Group;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagGroup;
use Drupal\metatag\Plugin\metatag\Group\GroupBase;

/**
 * The open graph group.
 */
#[MetatagGroup(
  id: 'open_graph',
  label: new TranslatableMarkup('Open Graph'),
  description: new TranslatableMarkup(
    'The <a href=":url">Open Graph meta tags</a> are used to control how Facebook, Pinterest, LinkedIn and other social networking sites interpret the site\'s content.<br><br>The Facebook <a href=":sharing_debugger">Sharing Debugger</a> lets you preview how your content will look when it\'s shared to Facebook and debug any issues with your Open Graph tags.',
    [
      ':url' => 'https://ogp.me/',
      ':sharing_debugger' => 'https://developers.facebook.com/tools/debug/',
    ],
  ),
  weight: 3,
)]
class OpenGraph extends GroupBase {
  // Inherits everything from Base.
}
