<?php

namespace Drupal\schema_course\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaTypeBase;

/**
 * Provides a plugin for the 'schema_course_type' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_course_type",
 *   label = @Translation("@type"),
 *   description = @Translation("The type of this Course"),
 *   name = "@type",
 *   group = "schema_course",
 *   weight = -50,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaCourseType extends SchemaTypeBase {

  /**
   * {@inheritdoc}
   */
  public static function labels() {
    return [
      'Course',
    ];
  }

}
