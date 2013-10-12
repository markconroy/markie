<?php
/**
 * @file
 * Contains the host_entity_id field handler.
 */

/**
 * Field handler to display the host_entity_id
 */
class field_collection_views_handler_field_host_entity_id extends views_handler_field {
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
   * Work out the host_entity_id
   */
  function render($values) {
    $host_entity_id = 0;
    //$item_id =  $this->get_value($values, 'item_id');
    $item_id =  $values->item_id;
    //debug($values);
    $field_collection_item = field_collection_item_load($item_id);
    $host_entity_id = $field_collection_item->hostEntityId();

    return $host_entity_id;
  }
}

