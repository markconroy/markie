<?php

namespace Drupal\entity_usage\UrlToEntityIntegrations;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\entity_usage\Events\Events;
use Drupal\entity_usage\Events\UrlToEntityEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Determines if the URL points to a public file managed as a file entity.
 */
class PublicFileIntegration implements EventSubscriberInterface {

  /**
   * The regex pattern to match requests to the public files directory.
   */
  private string $publicFilePattern;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'stream_wrapper.public')]
    StreamWrapperInterface $publicStream,
  ) {
    $baseUrl = $publicStream->getExternalUrl();
    $parsed = parse_url($baseUrl);

    if (isset($parsed['path'])) {
      $this->publicFilePattern = '{^' . preg_quote($parsed['path'], '{}') . '/}';
    }
    else {
      throw new \LogicException('The public stream wrapper does not provide a valid external URL.');
    }

  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [Events::URL_TO_ENTITY => ['getFileFromPath', 500]];
  }

  /**
   * Determines if the URL points to a public file managed as a file entity.
   *
   * @param \Drupal\entity_usage\Events\UrlToEntityEvent $event
   *   The event.
   */
  public function getFileFromPath(UrlToEntityEvent $event): void {
    if (!$event->isEntityTypeTracked('file')) {
      return;
    }

    $url = $event->getRequest()->getPathInfo();
    if (preg_match($this->publicFilePattern, $url)) {
      // Check if we can map the link to a public file.
      $file_uri = preg_replace($this->publicFilePattern, 'public://', urldecode($url));
      $files = $this->entityTypeManager->getStorage('file')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('uri', $file_uri)
        ->range(0, 1)
        ->execute();
      if (!empty($files)) {
        // File entity found.
        $event->setEntityInfo('file', reset($files));
      }
    }
  }

}
