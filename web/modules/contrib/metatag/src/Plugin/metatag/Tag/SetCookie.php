<?php

namespace Drupal\metatag\Plugin\metatag\Tag;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\metatag\Attribute\MetatagTag;
use Drupal\metatag\MetatagType;

/**
 * Provides a plugin for the 'set-cookie' meta tag.
 *
 * @deprecated in metatag:2.2.1 and is removed from metatag:3.0.0. No replacement is provided.
 *
 * @see https://www.drupal.org/project/metatag/issues/3582721
 * @see https://www.drupal.org/project/metatag/issues/3620190
 */
#[MetatagTag(
  id: 'set_cookie',
  label: new TranslatableMarkup('Set cookie (deprecated)'),
  name: 'set-cookie',
  group: 'advanced',
  type: MetatagType::String,
  description: new TranslatableMarkup(
    "Sets a cookie on the visitor's browser. Can be in either NAME=VALUE format, or a more verbose format including the path and expiration date. See <a href=\":url\">MDN documentation on Set-Cookie</a> for full details on the syntax. No longer supported in modern browsers.",
    [':url' => 'https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie'],
  ),
  weight: 5,
)]
class SetCookie extends MetaHttpEquivBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
