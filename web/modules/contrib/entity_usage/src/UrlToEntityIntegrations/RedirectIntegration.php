<?php

namespace Drupal\entity_usage\UrlToEntityIntegrations;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageInterface;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Drupal\redirect\Entity\Redirect;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * An event subscriber to find redirect entities from URLs.
 */
class RedirectIntegration implements EventSubscriberInterface {

  /**
   * Redirect configuration.
   */
  private readonly Config $config;

  public function __construct(
    private readonly Connection $connection,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->config = $configFactory->get('redirect.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [Events::URL_TO_ENTITY => ['findRedirectByUrl', -100]];
  }

  /**
   * Determines if a URL points to a redirect.
   *
   * @param \Drupal\entity_usage\Events\UrlToEntityEvent $event
   *   The event.
   *
   * @see \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber::onKernelRequestCheckRedirect()
   */
  public function findRedirectByUrl(UrlToEntityEvent $event): void {
    // There is nothing to do.
    if (!$event->isEntityTypeTracked('redirect')) {
      return;
    }

    $request = $event->getRequest();

    // Get URL info and process it to be used for hash generation.
    $request_query = $request->query->all();

    if (str_starts_with($request->getPathInfo(), '/system/files/') && !$request->query->has('file')) {
      // Private files paths are split by the inbound path processor and the
      // relative file path is moved to the 'file' query string parameter. This
      // is because the route system does not allow an arbitrary amount of
      // parameters. We preserve the path as is returned by the request object.
      // @see \Drupal\system\PathProcessor\PathProcessorFiles::processInbound()
      $path = $request->getPathInfo();
    }
    else {
      // Strip the query.
      $path = UrlHelper::parse($event->pathProcessedUrl)['path'];
    }
    $entity_id = $this->findMatchingRedirect($path, $request_query, $event->getLangcode());
    if (is_int($entity_id)) {
      $event->setEntityInfo('redirect', $entity_id);
    }
  }

  /**
   * Gets a redirect for given path, query and language.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param mixed[] $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   *
   * @return int|null
   *   The matched redirect entity ID or NULL if no redirect was found.
   *
   * @see \Drupal\redirect\RedirectRepository::findMatchingRedirect()
   */
  private function findMatchingRedirect($source_path, array $query, string $language): ?int {
    $source_path = trim($source_path, '/');
    $hashes = [Redirect::generateHash($source_path, $query, $language)];
    if ($language != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
      $hashes[] = Redirect::generateHash($source_path, $query, LanguageInterface::LANGCODE_NOT_SPECIFIED);
    }

    // Add a hash without the query string if passthrough is configured.
    if (!empty($query) && $this->config->get('passthrough_querystring')) {
      $hashes[] = Redirect::generateHash($source_path, [], $language);
      if ($language != LanguageInterface::LANGCODE_NOT_SPECIFIED) {
        $hashes[] = Redirect::generateHash($source_path, [], LanguageInterface::LANGCODE_NOT_SPECIFIED);
      }
    }

    // Load redirects by hash. A direct query is used to improve performance.
    $rid = $this->connection->query('SELECT rid FROM {redirect} WHERE hash IN (:hashes[]) ORDER BY LENGTH(redirect_source__query) DESC', [':hashes[]' => $hashes])->fetchField();
    return $rid === FALSE ? NULL : (int) $rid;
  }

}
