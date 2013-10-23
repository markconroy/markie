<?php
/**
 * @file
 * content_type_articles.features.inc
 */

/**
 * Implements hook_node_info().
 */
function content_type_articles_node_info() {
  $items = array(
    'artlcles' => array(
      'name' => t('Artlcles'),
      'base' => 'node_content',
      'description' => t('We use this to create articles for the webiste. Taxonomies will be used to all for categorising content.'),
      'has_title' => '1',
      'title_label' => t('Title'),
      'help' => '',
    ),
  );
  return $items;
}
