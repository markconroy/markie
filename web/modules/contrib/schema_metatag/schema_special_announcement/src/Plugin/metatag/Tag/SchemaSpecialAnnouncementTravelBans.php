<?php

namespace Drupal\schema_special_announcement\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Plugin for 'schema_special_announcement_travel_bans' meta tag.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_special_announcement_travel_bans",
 *   label = @Translation("travelBans"),
 *   description = @Translation("Url to information about travel bans in the context of COVID-19, if applicable to the announcement."),
 *   name = "travelBans",
 *   group = "schema_special_announcement",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaSpecialAnnouncementTravelBans extends SchemaNameBase {

}
