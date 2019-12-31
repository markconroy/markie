<?php

/**
 * @file
 * Drush integration for vem_migrate_oembed.
 */

/**
 * Implements hook_drush_command().
 */
function vem_migrate_oembed_drush_command() {

  $items['video-embed-media-migrate-oembed'] = [
    'description' => 'Migrate from VEF to oEmbed in core.',
    'aliases' => ['vemmo'],
    'core' => ['8+'],
    'examples' => [
      "drush vemmo" => "Migrate from VEF to oEmbed in core",
    ],
  ];

  return $items;
}

/**
 * Callback for drush commmand "video-embed-media-migrate-oembed" (vemmo).
 */
function drush_vem_migrate_oembed_video_embed_media_migrate_oembed() {

  /** @var \Drupal\vem_migrate_oembed\VemMigrate $migrate */
  $migrate = \Drupal::service('vem_migrate_oembed.migrate');
  $migrate->migrate();
}
