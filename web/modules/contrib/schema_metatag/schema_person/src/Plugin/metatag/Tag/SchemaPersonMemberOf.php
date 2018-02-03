<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'schema_person_member_of' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_member_of",
 *   label = @Translation("memberOf"),
 *   description = @Translation("An Organization (or ProgramMembership) to which this Person or Organization belongs."),
 *   name = "memberOf",
 *   group = "schema_person",
 *   weight = 30,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonMemberOf extends SchemaPersonOrgBase {

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
