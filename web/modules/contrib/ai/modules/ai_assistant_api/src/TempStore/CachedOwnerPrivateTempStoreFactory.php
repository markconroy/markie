<?php

namespace Drupal\ai_assistant_api\TempStore;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Creates private temp stores that cache their owner for the request.
 *
 * Unlike the core factory, the created store is cached per collection, so
 * repeated calls for the same collection return the same instance and
 * therefore share one cached owner across the request. See
 * \Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore for why the
 * owner needs to be cached at all.
 */
class CachedOwnerPrivateTempStoreFactory extends PrivateTempStoreFactory {

  /**
   * The already instantiated temp stores, keyed by collection.
   *
   * @var \Drupal\ai_assistant_api\TempStore\CachedOwnerPrivateTempStore[]
   */
  protected array $stores = [];

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    if (!isset($this->stores[$collection])) {
      $storage = $this->storageFactory->get("tempstore.private.$collection");
      $this->stores[$collection] = new CachedOwnerPrivateTempStore($storage, $this->lockBackend, $this->currentUser, $this->requestStack, $this->expire);
    }
    return $this->stores[$collection];
  }

}
