<?php

namespace Drupal\schema_metatag\Plugin\metatag\Tag;

use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\views\Views;
use Drupal\Core\Url;

/**
 * All Schema.org views itemListElement tags should extend this class.
 */
class SchemaItemListElementViewsBase extends SchemaItemListElementBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['#description'] = $this->t("Provide the machine name of the view, the machine name of the display, and any argument values, separated by colons, i.e. 'view_name:display_id' or 'view_name:display_id:article'. Use 'view_name:display_id:{{args}}' to pass the page arguments to the view. This will create a <a href=':url'>Summary View</a> list, which assumes each list item contains the url to a view page for the entity. The view rows should contain content (like teaser views) rather than fields for this to work correctly.", [':url' => 'https://developers.google.com/search/docs/guides/mark-up-listings']);
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
    $args = explode(':', $input_value);
    if (empty($args)) {
      return $values;
    }

    // Load the requested view.
    $view_id = array_shift($args);
    $view = Views::getView($view_id);

    // Set the display.
    if (count($args) > 0) {
      $display_id = array_shift($args);
      $view->setDisplay($display_id);
    }
    else {
      $view->initDisplay();
    }

    // See if the page's arguments should be passed to the view.
    if (count($args) == 1 && $args[0] == '{{args}}') {
      $view_path = explode("/", $view->getPath());
      $current_url = Url::fromRoute('<current>');
      $query_args = explode("/", substr($current_url->toString(), 1));

      $args = [];
      foreach ($query_args as $index => $arg) {
        if (in_array($arg, $view_path)) {
          unset($query_args[$index]);
        }
      }
      if (!empty($query_args)) {
        $args = array_values($query_args);
      }
    }

    // Allow modules to alter the arguments passed to the view.
    \Drupal::moduleHandler()->alter('schema_item_list_views_args', $args);

    if (!empty($args)) {
      $view->setArguments($args);
    }

    $view->preExecute();
    $view->execute();
    // Get the view results.
    $key = 1;
    foreach ($view->result as $item) {
      // If this is a display that does not provide an entity in the result,
      // there is really nothing more to do.
      $entity = static::getEntityFromRow($item);
      if (!$entity) {
        return '';
      }
      // Get the absolute path to this entity.
      $url = $entity->toUrl()->setAbsolute()->toString();
      $values[$key] = [
        '@id' => $url,
        'name' => $entity->label(),
        'url' => $url,
      ];
      $key++;
    }
    return $values;
  }

  /**
   * Tries to retrieve an entity from a Views row.
   *
   * @param $row
   *   The Views row
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  protected static function getEntityFromRow($row) {
    if (!empty($row->_entity)) {
      return $row->_entity;
    }

    if (isset($row->_object) && $row->_object instanceof EntityAdapter) {
      return $row->_object->getValue();
    }

    return NULL;
  }

}
