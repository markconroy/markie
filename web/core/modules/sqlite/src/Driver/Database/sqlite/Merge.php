<?php

namespace Drupal\sqlite\Driver\Database\sqlite;

use Drupal\Core\Database\Query\Merge as QueryMerge;

@trigger_error('Extending from \Drupal\sqlite\Driver\Database\sqlite\Merge is deprecated in drupal:11.0.0 and is removed from drupal:12.0.0. Extend from the base class instead. See https://www.drupal.org/node/3256524', E_USER_DEPRECATED);

/**
 * SQLite implementation of \Drupal\Core\Database\Query\Merge.
 */
class Merge extends QueryMerge {
}
