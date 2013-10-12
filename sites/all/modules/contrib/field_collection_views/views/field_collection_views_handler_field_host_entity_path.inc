<?php
/**
 * @file
 * Contains the host_entity_path field handler.
 */
 
/**
 * Field handler to display the host_entity_path
 */
class field_collection_views_handler_field_host_entity_path extends views_handler_field {
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
   * Work out the host_entity_path
   */
  function render($values) {
    $host_entity_path = "";
    $item_id =  $values->item_id;
    //debug($values);
    $field_collection_item = field_collection_item_load($item_id);
    $host_entity_id = $field_collection_item->hostEntityId();
    if (!empty($host_entity_id)) {
      $host_entity_type = $field_collection_item->hostEntityType();
      if (!empty($host_entity_type)) {
        $host_entitys = entity_load($host_entity_type, array($host_entity_id));
        if (isset($host_entitys[$host_entity_id])) {
          $host_entity = $host_entitys[$host_entity_id];
          //debug($host_entity);
          //$host_entity = profile2_load($host_entity_id);
          $host_entity_uri = entity_uri($host_entity_type, $host_entity);
          $host_entity_path = isset($host_entity_uri['path'])?$host_entity_uri['path']:"";
        }
      }
    }
    return $host_entity_path;
  }
}
