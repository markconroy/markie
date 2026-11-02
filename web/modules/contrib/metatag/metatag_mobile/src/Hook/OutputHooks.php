<?php

namespace Drupal\metatag_mobile\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_mobile.
 */
class OutputHooks {

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments) {
    // @todo This does not seem to still work, see HandheldFriendly.
    if (isset($attachments['#attached']['html_head'])) {
      // A list of core tags that will be replaced, in the format:
      // core tag => Metatag-supplied tag(s)
      // This assumes that the module's meta tags are output *before* the core
      // tags, if this changes then We're Going To Have A Bad Time. Also, the
      // Metatag output can have a "_0" suffix, which is why they need to be in
      // an array.
      $dupes = [
        'MobileOptimized' => [
          'mobileoptimized',
          'mobileoptimized_0',
        ],
        'HandheldFriendly' => [
          'handheldfriendly',
          'handheldfriendly_0',
        ],
        'viewport' => [
          'viewport',
        ],
      ];
      // Keep track of when the Metatag-supplied meta tags are found, so if the
      // core tag is also found it can be removed.
      $found = [];
      foreach ($dupes as $core_tag => $meta_tags) {
        foreach ($attachments['#attached']['html_head'] as $key => $item) {
          if (isset($item[1])) {
            // The Metatag values are output before core's, so skip the first
            // item found so it can be picked up as the dupe; this is important
            // for the "viewport" meta tag where both core and Metatag use the
            // same name.
            if (in_array($item[1], $meta_tags) && !isset($found[$core_tag])) {
              $found[$core_tag] = $key;
            }
            elseif ($item[1] == $core_tag && isset($found[$core_tag])) {
              // @todo This ought to work, but doesn't?
              // @code
              // $attachments['#attached']['html_head'][$key]['#access'] =
              // FALSE;
              // @endcode
              unset($attachments['#attached']['html_head'][$key]);
            }
          }
        }
      }
    }
  }

}
