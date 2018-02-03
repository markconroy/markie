<?php

namespace Drupal\schema_organization\Plugin\metatag\Group;

use Drupal\schema_metatag\Plugin\metatag\Group\SchemaGroupBase;

/**
 * Provides a plugin for the 'Organization' meta tag group.
 *
 * @MetatagGroup(
 *   id = "schema_organization",
 *   label = @Translation("Schema.org: Organization"),
 *   description = @Translation("See Schema.org definitions for this Schema type at <a href="":url"">:url</a>.", arguments = { ":url" = "http://schema.org/Organization"}),
 *   weight = 10,
 * )
 */
class SchemaOrganization extends SchemaGroupBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
