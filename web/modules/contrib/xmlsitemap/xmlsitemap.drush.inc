<?php

/**
 * @file
 * Drush integration functions for the xmlsitemap module.
 *
 * @ingroup xmlsitemap
 */

/**
 * Implements hook_drush_command().
 */
function xmlsitemap_drush_command() {
  $items['xmlsitemap-regenerate'] = [
    'description' => 'Regenerate the XML sitemap files.',
    'callback' => 'drush_xmlsitemap_regenerate',
    'drupal dependencies' => ['xmlsitemap'],
  ];
  $items['xmlsitemap-rebuild'] = [
    'description' => 'Dump and re-process all possible XML sitemap data, and then regenerate the files.',
    'callback' => 'drush_xmlsitemap_rebuild',
    'drupal dependencies' => ['xmlsitemap'],
  ];
  $items['xmlsitemap-index'] = [
    'description' => 'Process un-indexed XML sitemap links.',
    'callback' => 'drush_xmlsitemap_index',
    'drupal dependencies' => ['xmlsitemap'],
    'options' => [
      'limit' => 'The limit of links of each type to process.',
    ],
  ];
  return $items;
}

/**
 * Regenerate the sitemap files from existing data.
 */
function drush_xmlsitemap_regenerate() {
  $batch = xmlsitemap_regenerate_batch();
  batch_set($batch);
  drush_backend_batch_process();
}

/**
 * Dump and rebuild all the sitemap data, then regenerate the files.
 */
function drush_xmlsitemap_rebuild() {
  // Build a list of rebuildable link types.
  $rebuild_types = xmlsitemap_get_rebuildable_link_types();
  if (empty($rebuild_types)) {
    return drush_log(dt('No link types are rebuildable.'), 'warning');
  }

  $batch = xmlsitemap_rebuild_batch($rebuild_types, TRUE);
  batch_set($batch);
  drush_backend_batch_process();
}

/**
 * Process un-indexed XML sitemap links.
 */
function drush_xmlsitemap_index() {
  $limit = (int) drush_get_option('limit', \Drupal::config('xmlsitemap.settings')->get('batch_limit'));
  $count_before = \Drupal::database()->select('xmlsitemap', 'x')->countQuery()->execute()->fetchField();

  \Drupal::moduleHandler()->invokeAll('xmlsitemap_index_links', ['limit' => $limit]);

  $count_after = \Drupal::database()->select('xmlsitemap', 'x')->countQuery()->execute()->fetchField();

  if ($count_after == $count_before) {
    drush_print(dt('No new XML sitemap links to index.'));
  }
  else {
    drush_print(dt('Indexed @count new XML sitemap links.', ['@count' => $count_after - $count_before]));
  }
}
