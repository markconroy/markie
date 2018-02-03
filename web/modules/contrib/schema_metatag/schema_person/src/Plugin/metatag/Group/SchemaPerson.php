<?php

namespace Drupal\schema_person\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Person' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_person",
 *   label = @Translation("Schema.org: Person"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/Person"}),
 *   weight = 10,
 * )
 */
class SchemaPerson extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
