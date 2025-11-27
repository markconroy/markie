<?php

namespace Drupal\simple_sitemap\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\simple_sitemap\Entity\SimpleSitemap;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes the inbound and outbound sitemap paths.
 */
class SitemapPathProcessor implements InboundPathProcessorInterface, OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    $args = explode('/', $path ?? '');
    if (count($args) === 3 && $args[2] === 'sitemap.xml'
    // Ensure variant is ASCII, becase core doesn't yet.
    // @see https://www.drupal.org/project/simple_sitemap/issues/3554196
    // @see https://www.drupal.org/project/drupal/issues/3475540
    && mb_check_encoding($args[1], 'ASCII')
    && SimpleSitemap::load($args[1])) {
      $path = '/sitemaps/' . $args[1] . '/sitemap.xml';
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    $args = explode('/', $path ?? '');
    if (count($args) === 4 && $args[1] === 'sitemaps' && $args[3] === 'sitemap.xml') {
      $path = '/' . $args[2] . '/sitemap.xml';
    }

    return $path;
  }

}
