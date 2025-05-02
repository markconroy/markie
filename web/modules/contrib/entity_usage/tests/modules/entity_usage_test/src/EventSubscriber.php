<?php

namespace Drupal\entity_usage_test;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\entity_usage\Events\EntityUsageEvent;
use Drupal\entity_usage\Events\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test event subscriber.
 */
class EventSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'keyvalue')] private KeyValueFactoryInterface $kf,
  ) {}

  /**
   * Stores an event in a key value store.
   *
   * @param \Drupal\entity_usage\Events\EntityUsageEvent $event
   *   The event to store.
   */
  public function register(EntityUsageEvent $event): void {
    $kv = $this->kf->get('entity_usage_test');
    $events = $kv->get('register', []);
    $events[] = $event;
    $kv->set('register', $events);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      Events::USAGE_REGISTER => 'register',
    ];
  }

}
