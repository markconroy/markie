<?php

namespace Drupal\entity_usage\Events;

/**
 * Contains all events thrown by Entity Usage.
 */
final class Events {

  /**
   * Occurs when usage records are added or updated.
   *
   * @var string
   */
  const USAGE_REGISTER = 'entity_usage.register';

  /**
   * Occurs when all records of a given target entity type are removed.
   *
   * @var string
   */
  const BULK_DELETE_DESTINATIONS = 'entity_usage.bulk_delete_targets';

  /**
   * Occurs when all records of a given source entity type are removed.
   *
   * @var string
   */
  const BULK_DELETE_SOURCES = 'entity_usage.bulk_delete_sources';

  /**
   * Occurs when all records from a given entity_type + field are deleted.
   *
   * @var string
   */
  const DELETE_BY_FIELD = 'entity_usage.delete_by_field';

  /**
   * Occurs when all records from a given source entity are deleted.
   *
   * @var string
   */
  const DELETE_BY_SOURCE_ENTITY = 'entity_usage.delete_by_source_entity';

  /**
   * Occurs when all records from a given target entity are deleted.
   *
   * @var string
   */
  const DELETE_BY_TARGET_ENTITY = 'entity_usage.delete_by_target_entity';

  /**
   * Occurs when we need to convert a URL string to an entity.
   *
   * @var string
   *
   * @see \Drupal\entity_usage\UrlToEntity
   * @see \Drupal\entity_usage\Events\UrlToEntityEvent
   */
  const URL_TO_ENTITY = 'entity_usage.url_to_entity';

}
