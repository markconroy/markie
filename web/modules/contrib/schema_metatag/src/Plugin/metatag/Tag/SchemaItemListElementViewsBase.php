<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\Core\Url;

/**
 * All Schema.org views itemListElement tags should extend this class.
 */
abstract class SchemaItemListElementViewsBase extends SchemaItemListElementBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#attributes']['placeholder'] = 'view_name:display_id';
    $form['#description'] = $this->t('To display a Views list in Schema.org structured data, provide the machine name of the view, and the machine name of the display, separated by a colon.');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function testValue() {
    return 'frontpage:page_1';
  }

  /**
   * {@inheritdoc}
   */
  public static function getItems($input_value) {
    $values = [];
    $ids = explode(':', $input_value);
    if (count($ids) == 2) {
      $view_id = $ids[0];
      $display_id = $ids[1];
      // Get the view results.
      if ($result = views_get_view_result($view_id, $display_id)) {
        $key = 1;
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
          $values[$key] = [
            '@id' => $url,
            'name' => $item->_entity->label(),
          ];
          $key++;
        }
      }
    }
    return $values;
  }

}
