<?php

namespace Drupal\schema_organization\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaImageBase;

/**
 * Provides a plugin for the 'logo' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_organization_logo",
 *   label = @Translation("logo"),
 *   description = @Translation("The logo for this organization."),
 *   name = "logo",
 *   group = "schema_organization",
 *   weight = 3,
 *   type = "image",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaOrganizationLogo extends SchemaImageBase {
  // Nothing here yet. Just a placeholder class for a plugin.
}
