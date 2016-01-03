<?php
/**
 * @file
 * views_articles.views_default.inc
 */

/**
 * Implements hook_views_default_views().
 */
function views_articles_views_default_views() {
  $export = array();

  $view = new view();
  $view->name = 'articles';
  $view->description = 'A view to list the articles on the website.';
  $view->tag = 'default';
  $view->base_table = 'node';
  $view->human_name = 'Articles';
  $view->core = 7;
  $view->api_version = '3.0';
  $view->disabled = FALSE; /* Edit this to true to make a default view disabled initially */

  /* Display: Master */
  $handler = $view->new_display('default', 'Master', 'default');
  $handler->display->display_options['title'] = 'Articles';
  $handler->display->display_options['use_more_always'] = FALSE;
  $handler->display->display_options['access']['type'] = 'perm';
  $handler->display->display_options['cache']['type'] = 'none';
  $handler->display->display_options['query']['type'] = 'views_query';
  $handler->display->display_options['exposed_form']['type'] = 'basic';
  $handler->display->display_options['pager']['type'] = 'full';
  $handler->display->display_options['pager']['options']['items_per_page'] = '10';
  $handler->display->display_options['style_plugin'] = 'default';
  $handler->display->display_options['row_plugin'] = 'fields';
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = '';
  $handler->display->display_options['fields']['title']['exclude'] = TRUE;
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['element_label_colon'] = FALSE;
  /* Field: Content: Body */
  $handler->display->display_options['fields']['field_art_body']['id'] = 'field_art_body';
  $handler->display->display_options['fields']['field_art_body']['table'] = 'field_data_field_art_body';
  $handler->display->display_options['fields']['field_art_body']['field'] = 'field_art_body';
  $handler->display->display_options['fields']['field_art_body']['label'] = '';
  $handler->display->display_options['fields']['field_art_body']['exclude'] = TRUE;
  $handler->display->display_options['fields']['field_art_body']['alter']['max_length'] = '250';
  $handler->display->display_options['fields']['field_art_body']['alter']['strip_tags'] = TRUE;
  $handler->display->display_options['fields']['field_art_body']['alter']['trim'] = TRUE;
  $handler->display->display_options['fields']['field_art_body']['alter']['preserve_tags'] = '<p> <br> <em>';
  $handler->display->display_options['fields']['field_art_body']['element_label_colon'] = FALSE;
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = '';
  $handler->display->display_options['fields']['view_node']['exclude'] = TRUE;
  $handler->display->display_options['fields']['view_node']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['view_node']['text'] = 'Hungry for More?';
  /* Field: Global: Custom text */
  $handler->display->display_options['fields']['nothing']['id'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['table'] = 'views';
  $handler->display->display_options['fields']['nothing']['field'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['label'] = '';
  $handler->display->display_options['fields']['nothing']['alter']['text'] = '<div class="views-articles-main-page-wrapper">
<div class="views-articles-main-page-title"><h2>[title]</h2></div>
<div class="views-articles-main-page-body">[field_art_body]</div>
<div class="views-articles-main-page-link">[view_node]</div>
</div>';
  $handler->display->display_options['fields']['nothing']['element_label_colon'] = FALSE;
  /* Sort criterion: Content: Post date */
  $handler->display->display_options['sorts']['created']['id'] = 'created';
  $handler->display->display_options['sorts']['created']['table'] = 'node';
  $handler->display->display_options['sorts']['created']['field'] = 'created';
  $handler->display->display_options['sorts']['created']['order'] = 'DESC';
  /* Filter criterion: Content: Published */
  $handler->display->display_options['filters']['status']['id'] = 'status';
  $handler->display->display_options['filters']['status']['table'] = 'node';
  $handler->display->display_options['filters']['status']['field'] = 'status';
  $handler->display->display_options['filters']['status']['value'] = 1;
  $handler->display->display_options['filters']['status']['group'] = 1;
  $handler->display->display_options['filters']['status']['expose']['operator'] = FALSE;
  /* Filter criterion: Content: Type */
  $handler->display->display_options['filters']['type']['id'] = 'type';
  $handler->display->display_options['filters']['type']['table'] = 'node';
  $handler->display->display_options['filters']['type']['field'] = 'type';
  $handler->display->display_options['filters']['type']['value'] = array(
    'artlcles' => 'artlcles',
  );

  /* Display: Page: Articles */
  $handler = $view->new_display('page', 'Page: Articles', 'page');
  $handler->display->display_options['metatags'] = array(
    'description' => array(
      'value' => 'Articles by Mark Conroy - Drupal developer, author, public speaker - Drupal, Literature, Education themed posts.',
    ),
    'keywords' => array(
      'value' => 'drupal, literature, education, web design, sass',
    ),
    'twitter:creator' => array(
      'value' => '@markconroy',
    ),
    'twitter:creator:id' => array(
      'value' => '16218176',
    ),
  );
  $handler->display->display_options['path'] = 'articles';
  $handler->display->display_options['menu']['type'] = 'normal';
  $handler->display->display_options['menu']['title'] = 'Articles';
  $handler->display->display_options['menu']['name'] = 'main-menu';

  /* Display: Block: Homepage */
  $handler = $view->new_display('block', 'Block: Homepage', 'block_1');
  $handler->display->display_options['defaults']['title'] = FALSE;
  $handler->display->display_options['title'] = 'Articles by Mark Conroy';
  $handler->display->display_options['defaults']['fields'] = FALSE;
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['label'] = '';
  $handler->display->display_options['fields']['title']['exclude'] = TRUE;
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['element_type'] = 'h2';
  $handler->display->display_options['fields']['title']['element_label_colon'] = FALSE;
  /* Field: Global: Custom text */
  $handler->display->display_options['fields']['nothing']['id'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['table'] = 'views';
  $handler->display->display_options['fields']['nothing']['field'] = 'nothing';
  $handler->display->display_options['fields']['nothing']['label'] = '';
  $handler->display->display_options['fields']['nothing']['alter']['text'] = '<div class="views-articles-home-block-wrapper">
<div class="views-articles-home-block-title"><h2>[title]</h2></div>
</div>';
  $handler->display->display_options['fields']['nothing']['element_label_colon'] = FALSE;
  $handler->display->display_options['block_description'] = 'Articles: Homepage';

  /* Display: Page: JSON */
  $handler = $view->new_display('page', 'Page: JSON', 'page_1');
  $handler->display->display_options['defaults']['style_plugin'] = FALSE;
  $handler->display->display_options['style_plugin'] = 'views_json';
  $handler->display->display_options['style_options']['plaintext_output'] = 1;
  $handler->display->display_options['style_options']['remove_newlines'] = 0;
  $handler->display->display_options['style_options']['jsonp_prefix'] = '';
  $handler->display->display_options['style_options']['using_views_api_mode'] = 0;
  $handler->display->display_options['style_options']['object_arrays'] = 0;
  $handler->display->display_options['style_options']['numeric_strings'] = 0;
  $handler->display->display_options['style_options']['bigint_string'] = 0;
  $handler->display->display_options['style_options']['pretty_print'] = 0;
  $handler->display->display_options['style_options']['unescaped_slashes'] = 0;
  $handler->display->display_options['style_options']['unescaped_unicode'] = 0;
  $handler->display->display_options['style_options']['char_encoding'] = array();
  $handler->display->display_options['defaults']['style_options'] = FALSE;
  $handler->display->display_options['defaults']['row_plugin'] = FALSE;
  $handler->display->display_options['defaults']['row_options'] = FALSE;
  $handler->display->display_options['defaults']['fields'] = FALSE;
  /* Field: Content: Title */
  $handler->display->display_options['fields']['title']['id'] = 'title';
  $handler->display->display_options['fields']['title']['table'] = 'node';
  $handler->display->display_options['fields']['title']['field'] = 'title';
  $handler->display->display_options['fields']['title']['alter']['word_boundary'] = FALSE;
  $handler->display->display_options['fields']['title']['alter']['ellipsis'] = FALSE;
  $handler->display->display_options['fields']['title']['element_label_colon'] = FALSE;
  /* Field: Content: Body */
  $handler->display->display_options['fields']['field_art_body']['id'] = 'field_art_body';
  $handler->display->display_options['fields']['field_art_body']['table'] = 'field_data_field_art_body';
  $handler->display->display_options['fields']['field_art_body']['field'] = 'field_art_body';
  $handler->display->display_options['fields']['field_art_body']['label'] = 'Summary';
  $handler->display->display_options['fields']['field_art_body']['alter']['max_length'] = '250';
  $handler->display->display_options['fields']['field_art_body']['alter']['strip_tags'] = TRUE;
  $handler->display->display_options['fields']['field_art_body']['alter']['trim'] = TRUE;
  $handler->display->display_options['fields']['field_art_body']['alter']['preserve_tags'] = '<p> <br> <em>';
  $handler->display->display_options['fields']['field_art_body']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['field_art_body']['type'] = 'text_trimmed';
  $handler->display->display_options['fields']['field_art_body']['settings'] = array(
    'trim_length' => '200',
  );
  /* Field: Content: Link */
  $handler->display->display_options['fields']['view_node']['id'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['table'] = 'views_entity_node';
  $handler->display->display_options['fields']['view_node']['field'] = 'view_node';
  $handler->display->display_options['fields']['view_node']['label'] = 'Read More';
  $handler->display->display_options['fields']['view_node']['element_label_colon'] = FALSE;
  $handler->display->display_options['fields']['view_node']['text'] = 'Hungry for More?';
  $handler->display->display_options['path'] = 'articles/feed/json';
  $export['articles'] = $view;

  return $export;
}
