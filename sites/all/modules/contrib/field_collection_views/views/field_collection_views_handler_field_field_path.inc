<?php
/**
 * @file
 * Contains the host_entity_path field handler.
 */

/**
 * Field handler to display the host_entity_path
 */
class field_collection_views_handler_field_field_path extends views_handler_field {
  function query() {
    // Do nothing, as this handler does not need to do anything to the query itself.
  } 

  function option_definition() {
    $options = parent::option_definition();
    return $options;
  }

  function options_form(&$form, &$form_state) {
    parent::options_form($form, $form_state);
  }

  /**
   * Work out the field_path
   */
  function render($values) {
    $field_path = "";
    $item_id =  $values->item_id;
    //debug($values);
    $field_collection_item = field_collection_item_load($item_id);
    $field_path = field_collection_field_get_path($field_collection_item->fieldInfo()); 

    return $field_path;
  }
}

