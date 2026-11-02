<?php

declare(strict_types=1);

namespace Drupal\ai_automators;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\user\UserInterface;

/**
 * The user automators run as when there is no current user.
 *
 * Saves from cron, drush or other code without a logged-in user run as this
 * user, or as anonymous if none is set. It is kept in state, since user ids
 * differ between environments.
 */
class AutomatorFallbackUser {

  /**
   * The state key that holds the user id.
   */
  const STATE_KEY = 'ai_automators.fallback_uid';

  /**
   * Constructor.
   */
  public function __construct(
    protected StateInterface $state,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Gets the id of the configured user.
   *
   * @return int|null
   *   The user id, or NULL if none is set.
   */
  public function getUid(): ?int {
    $uid = $this->state->get(static::STATE_KEY);
    return $uid ? (int) $uid : NULL;
  }

  /**
   * Sets the configured user.
   *
   * @param int|null $uid
   *   The user id, or NULL to run as anonymous.
   */
  public function setUid(?int $uid): void {
    if ($uid) {
      $this->state->set(static::STATE_KEY, $uid);
    }
    else {
      $this->state->delete(static::STATE_KEY);
    }
  }

  /**
   * Gets the configured user if it can be used.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user, or NULL if none is set or it is missing or blocked, in which
   *   case automators run as anonymous.
   */
  public function getAccount(): ?UserInterface {
    $uid = $this->getUid();
    if (!$uid) {
      return NULL;
    }
    /** @var \Drupal\user\UserInterface|null $account */
    $account = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$account || !$account->isActive()) {
      $this->loggerFactory->get('ai_automator')->warning('The fallback user %uid for automators is missing or blocked, running as anonymous instead.', [
        '%uid' => $uid,
      ]);
      return NULL;
    }
    return $account;
  }

}
