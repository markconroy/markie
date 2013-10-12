<?php
/**
 * @file
 * Provide default_views for field_collection.
 */

/**
 * Implementation of hook_views_default_views().
 */
function field_collection_views_views_default_views() {
  $view = new view;
  $view->name = 'field_collection_view';
  $view->description = '';
  $view->tag = 'default';
  $view->base_table = 'field_collection_item';
  $view->human_name = 'field collection view';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['access']['type'] = 'none';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['query']['options']['distinct'] = TRUE;
  $handler->display->display_options['query']['options']['query_comment'] = FALSE;
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'full';
  $handler->display->display_options['style_plugin'] = 'table';
  $handler->display->display_options['style_options']['columns'] = array(
    'item_id' => 'item_id',
    'field_resume_job_title' => 'field_resume_job_title',
    'field_resume_time_period' => 'field_resume_time_period',
  );
  $handler->display->display_options['style_options']['default'] = '-1';
  $handler->display->display_options['style_options']['info'] = array(
    'item_id' => array(
      'sortable' => 0,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
    ),
    'field_resume_job_title' => array(
      'sortable' => 0,
      'default_sort_order' => 'asc',
      'align' => '',
      'separator' => '',
    ),
    'field_resume_time_period' => array(
      'align' => '',
      'separator' => '',
    ),
  );
  $handler->display->display_options['style_options']['override'] = 1;
  $handler->display->display_options['style_options']['sticky'] = 0;
  $handler->display->display_options['style_options']['empty_table'] = 0;
  /* Footer: Global: Text area */
  $handler->display->display_options['footer']['area']['id'] = 'area';
  $handler->display->display_options['footer']['area']['table'] = 'views';
  $handler->display->display_options['footer']['area']['field'] = 'area';
  $handler->display->display_options['footer']['area']['empty'] = FALSE;
  $handler->display->display_options['footer']['area']['content'] = '[nothing_2]';
  $handler->display->display_options['footer']['area']['format'] = 'filtered_html';
  $handler->display->display_options['footer']['area']['tokenize'] = 1;
  /* Field: Field-collection item: Field-collection item ID */
  $handler->display->display_options['fields']['item_id']['id'] = 'item_id';
  $handler->display->display_options['fields']['item_id']['table'] = 'field_collection_item';
  $handler->display->display_options['fields']['item_id']['field'] = 'item_id';
  $handler->display->display_options['fields']['item_id']['alter']['alter_text'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['make_link'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['external'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['item_id']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['item_id']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['item_id']['alter']['html'] = 0;
  $handler->display->display_options['fields']['item_id']['element_label_colon'] = 1;
  $handler->display->display_options['fields']['item_id']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['item_id']['hide_empty'] = 0;
  $handler->display->display_options['fields']['item_id']['empty_zero'] = 0;
  $handler->display->display_options['fields']['item_id']['hide_alter_empty'] = 0;
  $handler->display->display_options['fields']['item_id']['separator'] = '';
  $handler->display->display_options['fields']['item_id']['format_plural'] = 0;
  /* Field: Field-collection item: Field path */
  $handler->display->display_options['fields']['field_path']['id'] = 'field_path';
  $handler->display->display_options['fields']['field_path']['table'] = 'field_collection_item';
  $handler->display->display_options['fields']['field_path']['field'] = 'field_path';
  $handler->display->display_options['fields']['field_path']['label'] = '';
  $handler->display->display_options['fields']['field_path']['exclude'] = TRUE;
  $handler->display->display_options['fields']['field_path']['alter']['alter_text'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['make_link'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['external'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['field_path']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['field_path']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['field_path']['alter']['html'] = 0;
  $handler->display->display_options['fields']['field_path']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['field_path']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['field_path']['hide_empty'] = 0;
  $handler->display->display_options['fields']['field_path']['empty_zero'] = 0;
  $handler->display->display_options['fields']['field_path']['hide_alter_empty'] = 0;
  /* Field: Field-collection item: Host Entity ID */
  $handler->display->display_options['fields']['host_entity_id']['id'] = 'host_entity_id';
  $handler->display->display_options['fields']['host_entity_id']['table'] = 'field_collection_item';
  $handler->display->display_options['fields']['host_entity_id']['field'] = 'host_entity_id';
  $handler->display->display_options['fields']['host_entity_id']['label'] = '';
  $handler->display->display_options['fields']['host_entity_id']['exclude'] = TRUE;
  $handler->display->display_options['fields']['host_entity_id']['alter']['alter_text'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['make_link'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['external'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['host_entity_id']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['host_entity_id']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['alter']['html'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['host_entity_id']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['host_entity_id']['hide_empty'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['empty_zero'] = 0;
  $handler->display->display_options['fields']['host_entity_id']['hide_alter_empty'] = 0;
  /* Field: Field-collection item: Host Entity Path */
  $handler->display->display_options['fields']['host_entity_path']['id'] = 'host_entity_path';
  $handler->display->display_options['fields']['host_entity_path']['table'] = 'field_collection_item';
  $handler->display->display_options['fields']['host_entity_path']['field'] = 'host_entity_path';
  $handler->display->display_options['fields']['host_entity_path']['label'] = '';
  $handler->display->display_options['fields']['host_entity_path']['exclude'] = TRUE;
  $handler->display->display_options['fields']['host_entity_path']['alter']['alter_text'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['make_link'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['external'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['host_entity_path']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['host_entity_path']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['alter']['html'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['host_entity_path']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['host_entity_path']['hide_empty'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['empty_zero'] = 0;
  $handler->display->display_options['fields']['host_entity_path']['hide_alter_empty'] = 0;
  /* Field: Field-collection item: Host Entity Type */
  $handler->display->display_options['fields']['host_entity_type']['id'] = 'host_entity_type';
  $handler->display->display_options['fields']['host_entity_type']['table'] = 'field_collection_item';
  $handler->display->display_options['fields']['host_entity_type']['field'] = 'host_entity_type';
  $handler->display->display_options['fields']['host_entity_type']['label'] = '';
  $handler->display->display_options['fields']['host_entity_type']['exclude'] = TRUE;
  $handler->display->display_options['fields']['host_entity_type']['alter']['alter_text'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['make_link'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['external'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['host_entity_type']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['host_entity_type']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['alter']['html'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['host_entity_type']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['host_entity_type']['hide_empty'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['empty_zero'] = 0;
  $handler->display->display_options['fields']['host_entity_type']['hide_alter_empty'] = 0;
  /* Field: Global: Custom text */
  $handler->display->display_options['fields']['nothing']['id'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['table'] = 'views';
  $handler->display->display_options['fields']['nothing']['field'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['label'] = 'Edit';
  $handler->display->display_options['fields']['nothing']['alter']['text'] = 'Edit';
  $handler->display->display_options['fields']['nothing']['alter']['make_link'] = 1;
  $handler->display->display_options['fields']['nothing']['alter']['path'] = '[field_path]/[item_id]/edit?destination=[host_entity_path]';
  $handler->display->display_options['fields']['nothing']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['external'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['nothing']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['nothing']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['nothing']['alter']['html'] = 0;
  $handler->display->display_options['fields']['nothing']['element_label_colon'] = 1;
  $handler->display->display_options['fields']['nothing']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['nothing']['hide_empty'] = 0;
  $handler->display->display_options['fields']['nothing']['empty_zero'] = 0;
  $handler->display->display_options['fields']['nothing']['hide_alter_empty'] = 0;
  /* Field: Global: Custom text */
  $handler->display->display_options['fields']['nothing_1']['id'] = 'nothing_1';
  $handler->display->display_options['fields']['nothing_1']['table'] = 'views';
  $handler->display->display_options['fields']['nothing_1']['field'] = 'nothing';
  $handler->display->display_options['fields']['nothing_1']['label'] = 'Delete';
  $handler->display->display_options['fields']['nothing_1']['alter']['text'] = 'Delete';
  $handler->display->display_options['fields']['nothing_1']['alter']['make_link'] = 1;
  $handler->display->display_options['fields']['nothing_1']['alter']['path'] = '[field_path]/[item_id]/delete?destination=[host_entity_path]';
  $handler->display->display_options['fields']['nothing_1']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['external'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['nothing_1']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['nothing_1']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['nothing_1']['alter']['html'] = 0;
  $handler->display->display_options['fields']['nothing_1']['element_label_colon'] = 1;
  $handler->display->display_options['fields']['nothing_1']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['nothing_1']['hide_empty'] = 0;
  $handler->display->display_options['fields']['nothing_1']['empty_zero'] = 0;
  $handler->display->display_options['fields']['nothing_1']['hide_alter_empty'] = 0;
  /* Field: Global: Custom text */
  $handler->display->display_options['fields']['nothing_2']['id'] = 'nothing_2';
  $handler->display->display_options['fields']['nothing_2']['table'] = 'views';
  $handler->display->display_options['fields']['nothing_2']['field'] = 'nothing';
  $handler->display->display_options['fields']['nothing_2']['label'] = '';
  $handler->display->display_options['fields']['nothing_2']['exclude'] = TRUE;
  $handler->display->display_options['fields']['nothing_2']['alter']['text'] = 'Add';
  $handler->display->display_options['fields']['nothing_2']['alter']['make_link'] = 1;
  $handler->display->display_options['fields']['nothing_2']['alter']['path'] = '[field_path]/add/[host_entity_type]/[host_entity_id]?destination=[host_entity_path]';
  $handler->display->display_options['fields']['nothing_2']['alter']['absolute'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['external'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['replace_spaces'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['trim_whitespace'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['nl2br'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['word_boundary'] = 1;
  $handler->display->display_options['fields']['nothing_2']['alter']['ellipsis'] = 1;
  $handler->display->display_options['fields']['nothing_2']['alter']['strip_tags'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['trim'] = 0;
  $handler->display->display_options['fields']['nothing_2']['alter']['html'] = 0;
  $handler->display->display_options['fields']['nothing_2']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['nothing_2']['element_default_classes'] = 1;
  $handler->display->display_options['fields']['nothing_2']['hide_empty'] = 0;
  $handler->display->display_options['fields']['nothing_2']['empty_zero'] = 0;
  $handler->display->display_options['fields']['nothing_2']['hide_alter_empty'] = 0;
  /* Sort criterion: Field-collection item: Field-collection item ID */
  $handler->display->display_options['sorts']['item_id']['id'] = 'item_id';
  $handler->display->display_options['sorts']['item_id']['table'] = 'field_collection_item';
  $handler->display->display_options['sorts']['item_id']['field'] = 'item_id';
  /* Contextual filter: Field-collection item: Field-collection item ID */
  $handler->display->display_options['arguments']['item_id']['id'] = 'item_id';
  $handler->display->display_options['arguments']['item_id']['table'] = 'field_collection_item';
  $handler->display->display_options['arguments']['item_id']['field'] = 'item_id';
  $handler->display->display_options['arguments']['item_id']['default_action'] = 'not found';
  $handler->display->display_options['arguments']['item_id']['default_argument_type'] = 'fixed';
  $handler->display->display_options['arguments']['item_id']['default_argument_skip_url'] = 0;
  $handler->display->display_options['arguments']['item_id']['summary']['number_of_records'] = '0';
  $handler->display->display_options['arguments']['item_id']['summary']['format'] = 'default_summary';
  $handler->display->display_options['arguments']['item_id']['summary_options']['items_per_page'] = '25';
  $handler->display->display_options['arguments']['item_id']['break_phrase'] = 1;
  $handler->display->display_options['arguments']['item_id']['not'] = 0;

  $views[$view->name] = $view;

  return $views;
}
