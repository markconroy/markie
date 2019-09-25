<?php

namespace Drupal\schema_movie\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaActionBase;

/**
 * Provides a plugin for the 'schema_movie_potential_action' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_movie_potential_action",
 *   label = @Translation("potentialAction"),
 *   description = @Translation("REQUIRED BY GOOGLE. Potential action provided by this work."),
 *   name = "potentialAction",
 *   group = "schema_movie",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaMoviePotentialAction extends SchemaActionBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {

    $this->actionTypes = ['ConsumeAction'];
    $this->actions = ['WatchAction', 'ViewAction'];

    $form = parent::form($element);
    return $form;

  }

}
