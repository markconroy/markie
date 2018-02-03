<?php

namespace Drupal\Tests\schema_recipe\Functional;

use Drupal\Tests\schema_metatag\Functional\SchemaMetatagTagsTestBase;

/**
 * Tests that each of the Schema Metatag Articles tags work correctly.
 *
 * @group schema_metatag
 * @group schema_recipe
 */
class SchemaRecipeTest extends SchemaMetatagTagsTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['schema_recipe'];

  /**
   * {@inheritdoc}
   */
  public $moduleName = 'schema_recipe';

  /**
   * {@inheritdoc}
   */
  public $schemaTagsNamespace = '\\Drupal\\schema_recipe\\Plugin\\metatag\\Tag\\';

  /**
   * {@inheritdoc}
   */
  public $schemaTags = [
    'schema_recipe_aggregate_rating' => 'SchemaRecipeAggregateRating',
    'schema_recipe_author' => 'SchemaRecipeAuthor',
    'schema_recipe_cook_time' => 'SchemaRecipeCookTime',
    'schema_recipe_date_published' => 'SchemaRecipeDatePublished',
    'schema_recipe_description' => 'SchemaRecipeDescription',
    'schema_recipe_image' => 'SchemaRecipeImage',
    'schema_recipe_name' => 'SchemaRecipeName',
    'schema_recipe_prep_time' => 'SchemaRecipePrepTime',
    'schema_recipe_recipe_category' => 'SchemaRecipeRecipeCategory',
    'schema_recipe_recipe_ingredient' => 'SchemaRecipeRecipeIngredient',
    'schema_recipe_recipe_instructions' => 'SchemaRecipeRecipeInstructions',
    'schema_recipe_recipe_yield' => 'SchemaRecipeRecipeYield',
    'schema_recipe_total_time' => 'SchemaRecipeTotalTime',
    'schema_recipe_type' => 'SchemaRecipeType',
  ];

}
