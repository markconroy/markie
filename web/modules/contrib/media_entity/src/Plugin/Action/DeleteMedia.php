<?php

namespace Drupal\media_entity\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\media\MediaInterface;

/**
 * Redirects to a media item deletion form.
 *
 * @Action(
 *   id = "media_delete_action",
 *   label = @Translation("Delete media"),
 *   type = "media",
 *   confirm_form_route_name = "entity.media.multiple_delete_confirm",
 * )
 */
class DeleteMedia extends ActionBase {

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
