<?php

namespace Drupal\entity_usage\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an entity_usage track annotation object.
 *
 * @see hook_entity_usage_track_info_alter()
 *
 * @Annotation
 */
class EntityUsageTrack extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the tracking method.
   *
   * @var \Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A brief description of the tracking method.
   *
   * @var \Drupal\Core\Annotation\Translation|string
   *
   * @ingroup plugin_translatable
   */
  public $description = '';

  /**
   * The field types that this plugin is able to track.
   *
   * @var string[]
   */
  public $field_types = [];

  /**
   * Determines what source entities the plugins support.
   *
   * Plugins that extend from \Drupal\entity_usage\EntityUsageTrackBase should
   * use \Drupal\Core\Entity\FieldableEntityInterface.
   *
   * @var class-string
   */
  public $source_entity_class;

}
