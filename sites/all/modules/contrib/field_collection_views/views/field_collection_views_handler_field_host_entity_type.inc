<?php
/**
 * @file
 * Contains the host_entity_path field handler.
 */

/**
 * Field handler to display the host_entity_path
 */
class field_collection_views_handler_field_host_entity_type extends views_handler_field {
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
   * Work out the host_entity_type
   */
  function render($values) {
    $host_entity_type = "";
    $item_id =  $values->item_id;
    //debug($values);
    $field_collection_item = field_collection_item_load($item_id);
    $host_entity_type = $field_collection_item->hostEntityType();
    return $host_entity_type;
  }
}

