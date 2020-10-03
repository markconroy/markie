<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_type",
 *   label = @Translation("@type"),
 *   description = @Translation("REQUIRED. The type of work."),
 *   name = "@type",
 *   group = "schema_movie",
 *   weight = -5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaMovieType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'Movie',
      'Series',
      '- EventSeries',
      '- CreativeWorkSeries',
      '-- BookSeries',
      '-- MovieSeries',
      '-- Periodical',
      '--- ComicSeries',
      '--- Newspaper',
      '-- PodcastSeries',
      '-- RadioSeries',
      '-- TVSeries',
      '-- VideoGameSeries',
      'CreativeWorkSeason',
      '- PodcastSeason',
      '- RadioSeason',
      '- TVSeason',
      'Episode',
      '- PodcastEpisode',
      '- RadioEpisode',
      '- TVEpisode',
    ];
  }

}
