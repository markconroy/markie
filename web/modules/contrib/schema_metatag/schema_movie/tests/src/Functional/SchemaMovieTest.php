<?php

namespace Drupal\Tests\schema_movie\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Movie tags work correctly.
 *
 * @group schema_metatag
 * @group schema_movie
 */
class SchemaMovieTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_movie'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_movie';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_movie\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_movie_actor' => 'SchemaMovieActor',
    'schema_movie_date_created' => 'SchemaMovieDateCreated',
    'schema_movie_description' => 'SchemaMovieDescription',
    'schema_movie_director' => 'SchemaMovieDirector',
    'schema_movie_duration' => 'SchemaMovieDuration',
    'schema_movie_image' => 'SchemaMovieImage',
    'schema_movie_music_by' => 'SchemaMovieMusicBy',
    'schema_movie_name' => 'SchemaMovieName',
    'schema_movie_producer' => 'SchemaMovieProducer',
    'schema_movie_production_company' => 'SchemaMovieProductionCompany',
    'schema_movie_type' => 'SchemaMovieType',
    'schema_movie_id' => 'SchemaMovieId',
    'schema_movie_url' => 'SchemaMovieUrl',
    'schema_movie_same_as' => 'SchemaMovieSameAs',
    'schema_movie_released_event' => 'SchemaMovieReleasedEvent',
    'schema_movie_potential_action' => 'SchemaMoviePotentialAction',
    'schema_movie_episode_number' => 'SchemaMovieEpisodeNumber',
    'schema_movie_season_number' => 'SchemaMovieSeasonNumber',
    'schema_movie_part_of_season' => 'SchemaMoviePartOfSeason',
    'schema_movie_part_of_series' => 'SchemaMoviePartOfSeries',
    'schema_movie_has_part' => 'SchemaMovieHasPart',
    'schema_movie_aggregate_rating' => 'SchemaMovieAggregateRating',
  ];

}
