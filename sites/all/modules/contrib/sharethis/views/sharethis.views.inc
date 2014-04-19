<?php

/**
 * @file
 * Views integration for ShareThis.
 */

/**
 * Implements hook_views_data().
 */
function sharethis_views_data() {
  $data = array();

  // ShareThis link.
  $data['node']['sharethis'] = array(
    'title' => t('ShareThis Link'),
    'help' => t('Link provided by the ShareThis service.'),
    'field' => array(
      'handler' => 'sharethis_handler_field_link',
      'click sortable' => FALSE,
    ),
  );

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function sharethis_views_data_alter(&$data) {
  if (module_exists('search_api_views')) {
    $entity_types = entity_get_info();
    foreach (search_api_index_load_multiple(FALSE) as $index) {
      $key = 'search_api_index_' . $index->machine_name;
      if (isset($data[$key])) {
        $data[$key]['sharethis'] = array(
          'title' => t('ShareThis Link'),
          'help' => t('Link provided by the ShareThis service.'),
          'field' => array(
            'handler' => 'sharethis_handler_field_link',
            'click sortable' => FALSE,
          ),
        );
      }
    }
  }
}
