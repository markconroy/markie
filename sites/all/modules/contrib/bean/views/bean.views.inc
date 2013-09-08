<?php

/**
 * Implements hook_views_data_alter().
 */
function bean_views_data_alter(&$data) {
  $data['bean']['type'] = array(
    'title' => t('Type'),
    'help' => t('The block type'),
    'field' => array(
      'handler' => 'views_handler_field_bean_type',
    ),
    'filter' => array(
      'handler' => 'views_handler_filter_bean_type',
    ),
  );

  $data['bean']['edit_link'] = array(
    'title' => t('Edit link'),
    'help' => t('Edit link for bean block'),
    'field' => array(
      'handler' => 'views_handler_field_bean_edit_link',
    ),
  );

  $data['bean']['delete_link'] = array(
    'title' => t('Delete link'),
    'help' => t('Delete link for bean block'),
    'field' => array(
      'handler' => 'views_handler_field_bean_delete_link',
    ),
  );
}
