<?php

namespace Drupal\ai_assistant_api\TempStore;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\TempStore\PrivateTempStore;
use Drupal\Core\TempStore\TempStoreException;

/**
 * Private tempstore that resolves its owner once per request.
 *
 * Streamed assistant responses keep writing to the tempstore after the
 * response has started sending, when the session has already been closed
 * and can no longer be read. Caching the owner on first access keeps those
 * later writes scoped to the right user or anonymous session.
 *
 * For anonymous users, the owner session key does not exist until the first
 * set(), so a get() that happens before any set() - for example while
 * looking for an existing thread - resolves to a NULL owner. That result is
 * not cached, only a real owner is, so a later set() (which creates the
 * session key itself) is still free to establish the owner that gets used
 * for the rest of the request.
 *
 * set() is overridden for the same reason: the parent implementation always
 * calls RequestStack::getSession() for anonymous users, even once the owner
 * is already cached. That throws once the session has been closed, which is
 * exactly the situation this class exists for, so the session is only
 * touched here while the owner still needs to be resolved.
 */
class CachedOwnerPrivateTempStore extends PrivateTempStore {

  /**
   * The session key holding the owner key for anonymous users.
   *
   * This reuses the same session key as the core private tempstore, so both
   * agree on one owner key per anonymous session.
   */
  const OWNER_SESSION_KEY = 'core.tempstore.private.owner';

  /**
   * The resolved owner of the store, cached for the request.
   *
   * @var int|string|null
   */
  protected int|string|null $owner = NULL;

  /**
   * {@inheritdoc}
   */
  protected function getOwner() {
    if ($this->owner === NULL) {
      $this->owner = parent::getOwner();
    }
    return $this->owner;
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    if ($this->owner === NULL && $this->currentUser->isAnonymous()) {
      $session = $this->requestStack->getSession();
      if (!$session->has(self::OWNER_SESSION_KEY)) {
        $session->set(self::OWNER_SESSION_KEY, Crypt::randomBytesBase64());
      }
    }

    $key = $this->createKey($key);
    if (!$this->lockBackend->acquire($key)) {
      $this->lockBackend->wait($key);
      if (!$this->lockBackend->acquire($key)) {
        throw new TempStoreException("Couldn't acquire lock to update item '$key' in '{$this->storage->getCollectionName()}' temporary storage.");
      }
    }

    $value = (object) [
      'owner' => $this->getOwner(),
      'data' => $value,
      'updated' => (int) $this->requestStack->getMainRequest()->server->get('REQUEST_TIME'),
    ];
    $this->storage->setWithExpire($key, $value, $this->expire);
    $this->lockBackend->release($key);
  }

}
