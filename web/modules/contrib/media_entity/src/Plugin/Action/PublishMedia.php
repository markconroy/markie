<?php

namespace Drupal\media_entity\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;

/**
 * Publishes a media item.
 *
 * @Action(
 *   id = "media_publish_action",
 *   label = @Translation("Publish media"),
 *   type = "media"
 * )
 */
class PublishMedia extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute(MediaInterface $entity = NULL) {}

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = AccessResult::allowed();
    return $return_as_object ? $access : $access->isAllowed();
  }

}
