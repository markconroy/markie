<?php

namespace Drupal\entity_usage_test\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\entity_usage\EntityUsageTrackBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests entity usage tracking.
 *
 * @EntityUsageTrack(
 *   id = "entity_usage_test",
 *   label = @Translation("Entity Usage test"),
 *   field_types = {
 *     "text_long",
 *   },
 *   source_entity_class = "Drupal\Core\Entity\EntityInterface"
 * )
 */
class TestPlugin extends EntityUsageTrackBase {

  /**
   * Key value store for testing.
   */
  private KeyValueStoreInterface $keyValue;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $static = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $static->keyValue = $container->get('keyvalue')->get('entity_usage_test');
    return $static;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    $return_value = [];
    $returns = $this->keyValue->get('returns', []);
    if (!empty($returns)) {
      $return_value = array_shift($returns);
      $this->keyValue->set('returns', $returns);
    }

    if ($return_value instanceof \Exception) {
      throw $return_value;
    }

    return $return_value;
  }

}
