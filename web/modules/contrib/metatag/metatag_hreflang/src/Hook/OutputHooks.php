<?php

namespace Drupal\metatag_hreflang\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for metatag_hreflang.
 */
class OutputHooks {

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments) {
    // Only bother doing anything if both the "html_head" and "html_head_link"
    // structures are present in the output.
    if (!empty($attachments['#attached']['html_head'])) {
      if (!empty($attachments['#attached']['html_head_link'])) {
        // Get all defined hreflang_per_language values from html_head.
        $hreflang_per_language = [];
        foreach ($attachments['#attached']['html_head'] as $element) {
          // Check for Metatag's identifier "hreflang_per_language".
          if (!empty($element[1])) {
            if (strpos($element[1], 'hreflang_per_language') !== FALSE && isset($element[0]['#attributes']['hreflang'])) {
              $hreflang_per_language[] = $element[0]['#attributes']['hreflang'];
            }
          }
        }
        // Remove default links coming from content_translation if already
        // defined by Metatag.
        foreach ($attachments['#attached']['html_head_link'] as $key => $element) {
          if (isset($element[0]['hreflang']) && in_array($element[0]['hreflang'], $hreflang_per_language)) {
            unset($attachments['#attached']['html_head_link'][$key]);
          }
        }
      }
    }
  }

}
