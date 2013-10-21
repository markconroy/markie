<?php
/**
 * @file
 * mark_s_test_content_type.features.inc
 */

/**
 * Implements hook_node_info().
 */
function mark_s_test_content_type_node_info() {
  $items = array(
    'mark_s_test' => array(
      'name' => t('mark\'s test'),
      'base' => 'node_content',
      'description' => '',
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
  );
  return $items;
}
