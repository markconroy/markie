<?php

namespace Drupal\stage_file_proxy\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Url;
use Drupal\image\Controller\ImageStyleDownloadController;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Drupal\stage_file_proxy\EventDispatcher\AlterExcludedPathsEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Stage file proxy subscriber for controller requests.
 */
class StageFileProxySubscriber implements EventSubscriberInterface {

  /**
   * Construct the FetchManager.
   *
   * @param \Drupal\stage_file_proxy\DownloadManagerInterface $manager
   *   The manager used to fetch the file against.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger interface.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $pageCacheKillSwitch
   *   The page cache kill switch.
   */
  public function __construct(
    protected DownloadManagerInterface $manager,
    protected LoggerInterface $logger,
    protected EventDispatcherInterface $eventDispatcher,
    protected ConfigFactoryInterface $configFactory,
    protected RequestStack $requestStack,
    protected KillSwitch $pageCacheKillSwitch,
  ) {
  }

  /**
   * Fetch the file from its origin.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The event to process.
   */
  public function checkFileOrigin(RequestEvent $event): void {
    $config = $this->configFactory->get('stage_file_proxy.settings');

    // Get the origin server.
    $server = $config->get('origin');

    // Quit if no origin given.
    if (!$server) {
      return;
    }

    if (str_ends_with($server, '/')) {
      $this->logger->error('Origin cannot end in /.');
      $server = rtrim($server, '/ ');
    }

    // Quit if we are the origin, ignore http(s).
    if (preg_replace('#^[a-z]*://#u', '', $server) === $event->getRequest()->getHost()) {
      return;
    }

    $file_dir = $this->manager->filePublicPath();
    $request_path = $event->getRequest()->getPathInfo();

    $request_path = mb_substr($request_path, 1);

    if (!str_starts_with($request_path, $file_dir)) {
      return;
    }

    // Moving to parent directory is insane here, so prevent that.
    if (in_array('..', explode('/', $request_path))) {
      return;
    }

    // Quit if the extension is in the list of excluded extensions.
    $excluded_extensions = $config->get('excluded_extensions') ?
      array_map('trim', explode(',', $config->get('excluded_extensions'))) : [];

    $extension = pathinfo($request_path)['extension'] ?? '';
    if (in_array($extension, $excluded_extensions)) {
      return;
    }

    $alter_excluded_paths_event = new AlterExcludedPathsEvent([]);
    $this->eventDispatcher->dispatch($alter_excluded_paths_event, 'stage_file_proxy.alter_excluded_paths');
    $excluded_paths = $alter_excluded_paths_event->getExcludedPaths();
    foreach ($excluded_paths as $excluded_path) {
      if (str_contains($request_path, $excluded_path)) {
        return;
      }
    }

    // Note if the origin server files location is different. This
    // must be the exact path for the remote site's public file
    // system path, and defaults to the local public file system path.
    $origin_dir = $config->get('origin_dir') ?? '';
    $remote_file_dir = trim($origin_dir);
    if ($remote_file_dir === '') {
      $remote_file_dir = $file_dir;
    }

    $request_path = rawurldecode($request_path);
    // Path relative to file directory. Used for hotlinking.
    $relative_path = mb_substr($request_path, mb_strlen($file_dir) + 1);
    // If file is fetched and use_imagecache_root is set, original is used.
    $paths = [$relative_path];

    // Image style file conversion support.
    $unconverted_path = substr(ImageStyleDownloadController::getUriWithoutConvertedExtension('public://' . $relative_path), strlen('public://'));
    if ($unconverted_path !== $relative_path) {
      if ($config->get('use_imagecache_root')) {
        // Check the unconverted file path first in order to use the local
        // original image.
        array_unshift($paths, $unconverted_path);
      }
      else {
        // Check the unconverted path after the image derivative.
        $paths[] = $unconverted_path;
      }
    }

    foreach ($paths as $relative_path) {
      $fetch_path = $relative_path;

      // Don't touch CSS and JS aggregation. 'css/' and 'js/' are hard coded to
      // match route definitions.
      // @see \Drupal\system\Routing\AssetRoutes
      if (str_starts_with($relative_path, 'css/') || str_starts_with($relative_path, 'js/')) {
        return;
      }

      // Is this imagecache? Request the root file and let imagecache resize.
      // We check this first so locally added files have precedence.
      $original_path = $this->manager->styleOriginalPath($relative_path);
      if ($original_path) {
        if (file_exists($original_path)) {
          // Imagecache can generate it without our help.
          return;
        }
        if ($config->get('use_imagecache_root') && $unconverted_path === $relative_path) {
          // Config says: Fetch the original.
          $fetch_path = StreamWrapperManager::getTarget($original_path);
        }
      }

      $query = $this->requestStack->getCurrentRequest()->query->all();
      $query_parameters = UrlHelper::filterQueryParameters($query);
      $proxy_headers = $config->get('proxy_headers') ?? '';
      $options = [
        'verify' => $config->get('verify'),
        'query' => $query_parameters,
        'headers' => $this->createProxyHeadersArray($proxy_headers),
      ];

      if ($config->get('hotlink')) {
        $relative_path = UrlHelper::encodePath($relative_path);
        $location = Url::fromUri("$server/$remote_file_dir/$relative_path", [
          'query' => $query_parameters,
          'absolute' => TRUE,
        ])->toString();
        $response = new TrustedRedirectResponse($location);
        $response->addCacheableDependency($config);
        $event->setResponse($response);
      }
      elseif ($this->manager->fetch($server, $remote_file_dir, $fetch_path, $options)) {
        // Refresh this request & let the web server work out mime type, etc.
        $location = Url::fromUri('base://' . $request_path, [
          'query' => $query_parameters,
          'absolute' => TRUE,
        ])->toString();

        // Ensure the redirect isn't cached by page_cache module.
        $this->pageCacheKillSwitch->trigger();

        // Use default cache control: must-revalidate, no-cache, private.
        $event->setResponse(new RedirectResponse($location));
      }
    }
  }

  /**
   * Registers the methods in this class that should be listeners.
   *
   * @return array
   *   An array of event listener definitions.
   */
  public static function getSubscribedEvents(): array {
    // Priority 240 is after ban middleware but before page cache.
    $events[KernelEvents::REQUEST][] = ['checkFileOrigin', 240];
    return $events;
  }

  /**
   * Helper function to generate HTTP headers array.
   *
   * @param string $headers_string
   *   Header string to break apart.
   *
   * @return array
   *   Any array for proxy headers.
   */
  protected function createProxyHeadersArray(string $headers_string): array {
    $lines = explode("\n", $headers_string);
    $headers = [];
    foreach ($lines as $line) {
      $header = explode('|', trim($line));
      if (count($header) > 1) {
        $headers[$header[0]] = $header[1];
      }
    }
    return $headers;
  }

}
