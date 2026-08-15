<?php

namespace Drupal\backup_migrate\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of Backup Destination entities.
 */
class DestinationListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Backup Destination');
    $header['id'] = $this->t('Machine name');
    $header['type'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['label'] = $entity->label();
    $row['id'] = $entity->id();
    $row['type'] = $entity->get('type');
    if ($plugin = $entity->getPlugin()) {
      $info = $plugin->getPluginDefinition();
      $row['type'] = $info['title'];
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Gets this list's default operations.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity the operations are for.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheability
   *   The cacheability metadata, if supported by the parent list builder.
   *
   * @return array
   *   The array structure is identical to the return value of
   *   self::getOperations().
   */
  public function getDefaultOperations(EntityInterface $entity, ?CacheableMetadata $cacheability = NULL) {
    $parent_method = new \ReflectionMethod(parent::class, __FUNCTION__);
    $arguments = [$entity];
    if ($parent_method->getNumberOfParameters() > 1) {
      $arguments[] = $cacheability;
    }
    $operations = parent::getDefaultOperations(...$arguments);
    if ($entity->access('backups') && $entity->hasLinkTemplate('backups')) {
      $operations['backups'] = [
        'title' => $this->t('List Backups'),
        'weight' => 100,
        'url' => $entity->toUrl('backups'),
      ];
    }

    return $operations;
  }

}
