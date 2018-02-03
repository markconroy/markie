<?php

namespace Drupal\schema_person\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the 'schema_person_job_title' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_person_job_title",
 *   label = @Translation("jobTitle"),
 *   description = @Translation("The job title of the person (for example, Financial Manager)."),
 *   name = "jobTitle",
 *   group = "schema_person",
 *   weight = -10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaPersonJobTitle extends SchemaNameBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
