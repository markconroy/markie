<?php

namespace Drupal\schema_votingapi\Plugin\metatag\Tag;

/**
 * Provides a plugin for 'schema_organization_aggregate_rating_votingapi'.
 *
 * - 'id' should be a globally unique id.
 * - 'name' should match the Schema.org element name.
 * - 'group' should match the id of the group that defines the Schema.org type.
 *
 * @MetatagTag(
 *   id = "schema_organization_aggregate_rating_votingapi",
 *   label = @Translation("AggregateRating for Voting API"),
 *   description = @Translation(""),
 *   name = "aggregateRating",
 *   group = "schema_organization",
 *   weight = 11,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE
 * )
 */
class SchemaOrganizationAggregateRatingVotingApi extends SchemaVotingapiAggregateRatingBase {

}
