<?php

namespace Drupal\entity_usage\Plugin\EntityUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\block_content\Plugin\Block\BlockContentBlock;
use Drupal\entity_usage\EntityUsageTrackBase;

/**
 * Tracks usage of entities related in block_field fields.
 *
 * @EntityUsageTrack(
 *   id = "block_field",
 *   label = @Translation("Block Field"),
 *   description = @Translation("Tracks relationships created with 'Block Field' fields."),
 *   field_types = {"block_field"},
 *   source_entity_class = "Drupal\Core\Entity\FieldableEntityInterface",
 * )
 */
class BlockField extends EntityUsageTrackBase {

  /**
   * {@inheritdoc}
   */
  public function getTargetEntities(FieldItemInterface $item): array {
    /** @var \Drupal\block_field\BlockFieldItemInterface $item */
    $block_instance = $item->getBlock();
    if (!$block_instance) {
      return [];
    }

    $target_type = NULL;
    $target_id = NULL;

    // If there is a view inside this block, track the view entity instead.
    if ($block_instance->getBaseId() === 'views_block' && $this->isEntityTypeTracked('view')) {
      [$view_name, $display_id] = explode('-', $block_instance->getDerivativeId(), 2);
      // @todo worth trying to track the display id as well?
      // At this point the view is supposed to exist. Only track it if so.
      $exists = (bool) $this->entityTypeManager->getStorage('view')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($this->entityTypeManager->getDefinition('view')->getKey('id'), $view_name)
        ->count()
        ->execute();
      if ($exists) {
        $target_type = 'view';
        $target_id = $view_name;
      }
    }
    elseif ($block_instance instanceof BlockContentBlock
      && $this->isEntityTypeTracked('block_content')
      && $uuid = $block_instance->getDerivativeId()) {

      $blocks = $this->entityTypeManager
        ->getStorage('block_content')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition($this->entityTypeManager->getDefinition('block_content')->getKey('uuid'), $uuid)
        ->execute();
      if (!empty($blocks)) {
        // Doing this here means that an initial save operation of a host entity
        // will likely not track this block, once it does not exist at this
        // point. However, it's preferable to miss that and ensure we only track
        // loadable entities.
        $target_id = reset($blocks);
        $target_type = 'block_content';
      }
    }

    return ($target_type && $target_id) ? [$target_type . '|' . $target_id] : [];
  }

}
