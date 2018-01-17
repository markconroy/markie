<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use \Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;
use \Drupal\Core\Url;

/**
 * Provides a plugin for the 'schema_item_list' meta tag.
 */
abstract class SchemaItemListBase extends SchemaNameBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = 'view_name:display_id';
    $form['#description'] .= $this->t('To display a Views list in Schema.org structured data, provide the machine name of the view, and the machine name of the display, separated by a colon.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function output() {
    $element = parent::output();
    if (!empty($element)) {
      $ids = explode(':', $this->value());
      $view_id = $ids[0];
      $display_id = $ids[1];
      // Get the view results.
      $result = views_get_view_result($view_id, $display_id);
      $key = 1;
      $element['#attributes']['content'] = [];
      foreach ($result as $item) {
        // If this is a display that does not provide an entity in the result,
        // there is really nothing more to do.
        if (empty($item->_entity)) {
           return '';
        }
        // Get the absolute path to this entity.
        // The entity that Views returns does not have the toUrl() method
        // which would be a little cleaner, but this works.
        $url = $item->_entity->url();
        $url = Url::fromUri('internal:' . $url)->setAbsolute()->toString();
        $element['#attributes']['content'][] = [
          '@type' => 'ListItem',
          'position' => $key,
          'name' => $item->_entity->label(),
          'url' => $url,
        ];
        $key++;
      }
    }
    return $element;
  }

}
