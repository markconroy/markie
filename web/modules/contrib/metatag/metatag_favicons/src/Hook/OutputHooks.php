<?php

namespace Drupal\metatag_favicons\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_favicons.
 */
class OutputHooks {

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments) {
    // Check html_head_link on attached tags in head.
    if (!isset($attachments['#attached']['html_head_link'])) {
      return;
    }
    // Remove the default shortcut icon if one was set by Metatag.
    $valid_meta_tags = [
      'shortcut icon',
      'shortcut_icon',
      'icon',
    ];
    foreach ($attachments['#attached']['html_head'] as $element) {
      if (isset($element[1]) && in_array($element[1], $valid_meta_tags)) {
        foreach ($attachments['#attached']['html_head_link'] as $key => $value) {
          if (isset($value[0]['rel']) && in_array($value[0]['rel'], $valid_meta_tags)) {
            unset($attachments['#attached']['html_head_link'][$key]);
          }
        }
      }
    }
  }

}
