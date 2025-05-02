<?php

namespace Drupal\entity_usage\UrlToEntityIntegrations;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\language\LanguageNegotiationMethodInterface;
use Drupal\language\LanguageNegotiatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber to determine the URL's langcode.
 */
class LanguageIntegration implements EventSubscriberInterface {

  /**
   * The language negotiation method for URLs.
   */
  private LanguageNegotiationMethodInterface $methodInstance;

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly ?LanguageNegotiatorInterface $languageNegotiator,
    private readonly AccountInterface $currentUser,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [Events::URL_TO_ENTITY => ['setLangcode', 100000]];
  }

  /**
   * Determines the langcode for the event's URL.
   *
   * @param \Drupal\entity_usage\Events\UrlToEntityEvent $event
   *   The event.
   */
  public function setLangcode(UrlToEntityEvent $event): void {
    $langcode = FALSE;
    if ($this->languageNegotiator) {
      $langcode = $this->getLanguageUrlMethod()->getLangcode($event->getRequest());
    }
    $event->setLangcode($langcode ?: $this->languageManager->getDefaultLanguage()->getId());
  }

  /**
   * Gets the language negotiation method for URLs.
   *
   * Note we cannot get this from the language manager because in tests the
   * language manager does not always have the current user set correctly.
   *
   * @return \Drupal\language\LanguageNegotiationMethodInterface
   *   The language negotiation method for URLs.
   */
  private function getLanguageUrlMethod(): LanguageNegotiationMethodInterface {
    // I think it is okay to store this on the event subscriber because
    // \Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl::getLangcode()
    // is not stateful.
    if (!isset($this->methodInstance)) {
      $this->languageNegotiator->setCurrentUser($this->currentUser);
      $this->methodInstance = $this->languageNegotiator->getNegotiationMethodInstance('language-url');
    }
    return $this->methodInstance;
  }

}
