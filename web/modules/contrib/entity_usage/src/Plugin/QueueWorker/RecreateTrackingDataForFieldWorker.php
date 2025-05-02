<?php

namespace Drupal\entity_usage\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\entity_usage\RecreateTrackingDataForFieldQueuer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * RecreateTrackingDataForFieldWorker class.
 *
 * A worker plugin to consume items from "entity_usage_recreate_tracking_data"
 * and update tracking info for each of them.
 *
 * @QueueWorker(
 *   id = "entity_usage_recreate_tracking_data",
 *   title = @Translation("Entity Usage Recreate Tracking Data for Field Queue"),
 *   cron = {"time" = 60}
 * )
 *
 * @see \Drupal\entity_usage\RecreateTrackingDataForFieldQueuer
 */
class RecreateTrackingDataForFieldWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RecreateTrackingDataForFieldQueuer $urlUpdateQueuer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(RecreateTrackingDataForFieldQueuer::class),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array{entity_type_id: string, entity_id: string, entity_revision_id: string, method: string, field_name: string} $data
   *   The data to process. Contains information about the entity, field and
   *   method to recalculate tracking data for.
   */
  public function processItem($data): void {
    $this->urlUpdateQueuer->processRecord(...$data);
  }

}
