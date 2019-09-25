<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'schema_person_affiliation' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_affiliation",
 *   label = @Translation("affiliation"),
 *   description = @Translation("An organization that this person is affiliated with. For example, a school/university, a club, or a team."),
 *   name = "affiliation",
 *   group = "schema_person",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonAffiliation extends SchemaPersonOrgBase {

  /**
   * Generate a form element for this meta tag.
   */
  public function form(array $element = []) {
    $form = parent::form($element);
    $form['name']['#attributes']['placeholder'] = '[site:name]';
    $form['url']['#attributes']['placeholder'] = '[site:url]';
    return $form;
  }

}
