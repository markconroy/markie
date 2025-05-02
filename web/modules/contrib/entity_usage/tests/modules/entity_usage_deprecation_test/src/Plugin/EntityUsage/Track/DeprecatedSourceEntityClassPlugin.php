<?php

namespace Drupal\entity_usage_deprecation_test\Plugin\EntityUsage\Track;

use Drupal\entity_usage\Plugin\EntityUsage\Track\HtmlLink;

/**
 * Tests entity usage tracking.
 *
 * @EntityUsageTrack(
 *   id = "entity_usage_test_deprecation",
 *   label = @Translation("Entity Usage test deprecation"),
 *   field_types = {
 *     "text_long",
 *   },
 * )
 */
class DeprecatedSourceEntityClassPlugin extends HtmlLink {
}
