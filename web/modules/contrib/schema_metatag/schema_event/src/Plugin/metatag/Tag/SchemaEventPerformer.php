<?php

namespace Drupal\schema_event\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaPersonOrgBase;

/**
 * Provides a plugin for the 'performer' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_event_performer",
 *   label = @Translation("performer"),
 *   description = @Translation("RECOMMENDED BY GOOGLE. The performer on the event."),
 *   name = "performer",
 *   group = "schema_event",
 *   weight = 10,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = TRUE
 * )
 */
class SchemaEventPerformer extends SchemaPersonOrgBase {

}
