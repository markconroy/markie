<?php

namespace Drupal\field_group;

use Drupal;

/**
 * Static methods for fieldgroup formatters.
 */
class FormatterHelper {

  /**
   * Return an array of field_group_formatter options.
   */
  public static function formatterOptions($type) {
    $options = &drupal_static(__FUNCTION__);

    if (!isset($options)) {
      $options = [];

      $manager = Drupal::service('plugin.manager.field_group.formatters');
      $formatters = $manager->getDefinitions();

      foreach ($formatters as $formatter) {
        if (in_array($type, $formatter['supported_contexts'])) {
          $options[$formatter['id']] = $formatter['label'];
        }
      }
    }

    return $options;
  }

}
