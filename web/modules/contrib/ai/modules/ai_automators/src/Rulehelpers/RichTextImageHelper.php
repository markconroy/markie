<?php

namespace Drupal\ai_automators\Rulehelpers;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;

/**
 * Helper for extracting image candidates from formatted text.
 */
class RichTextImageHelper {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
  ) {
  }

  /**
   * Parses image candidates from formatted HTML.
   *
   * @param string $html
   *   The formatted text HTML.
   * @param bool $includeExternal
   *   If external image URLs should be included.
   * @param int $maxImages
   *   Max amount of images to parse.
   *
   * @return array<int,array<string,mixed>>
   *   Parsed image candidates.
   */
  public function extractImageCandidates(string $html, bool $includeExternal = FALSE, int $maxImages = 5): array {
    $summary = $this->extractImageCandidateSummary($html, $includeExternal, $maxImages);
    return $summary['candidates'];
  }

  /**
   * Parses image candidates and returns summary counts.
   *
   * @param string $html
   *   The formatted text HTML.
   * @param bool $includeExternal
   *   If external image URLs should be included.
   * @param int $maxImages
   *   Max amount of images to parse.
   *
   * @return array<string,mixed>
   *   Candidate summary and capped candidate list.
   */
  public function extractImageCandidateSummary(string $html, bool $includeExternal = FALSE, int $maxImages = 5): array {
    $maxImages = max(0, $maxImages);
    $encounteredCount = $this->countEncounteredImageElements($html);
    $allCandidates = $this->collectAllImageCandidates($html, $includeExternal);
    $totalCount = count($allCandidates);
    $candidates = $maxImages === 0 ? [] : array_slice($allCandidates, 0, $maxImages);
    $processedCount = count($candidates);
    $skippedCount = max(0, $totalCount - $processedCount);
    $unprocessedCount = max(0, $encounteredCount - $processedCount);

    return [
      'candidates' => $candidates,
      'encountered_count' => $encounteredCount,
      'total_count' => $totalCount,
      'processed_count' => $processedCount,
      'skipped_count' => $skippedCount,
      'limit_exceeded' => $skippedCount > 0,
      'unprocessed_count' => $unprocessedCount,
      'has_unprocessed_images' => $unprocessedCount > 0,
    ];
  }

  /**
   * Counts encountered image-like elements in raw HTML.
   */
  protected function countEncounteredImageElements(string $html): int {
    if (trim($html) === '') {
      return 0;
    }

    $dom = Html::load($html);
    $xpath = new \DOMXPath($dom);
    $imgCount = $xpath->query('//img')->length;
    $mediaCount = $xpath->query('//drupal-media[@data-entity-uuid]')->length;
    return (int) ($imgCount + $mediaCount);
  }

  /**
   * Collects all eligible image candidates from formatted HTML.
   *
   * @return array<int,array<string,mixed>>
   *   Parsed image candidates.
   */
  protected function collectAllImageCandidates(string $html, bool $includeExternal = FALSE): array {
    $candidates = [];
    $keys = [];
    if (trim($html) === '') {
      return [];
    }

    $dom = Html::load($html);
    $xpath = new \DOMXPath($dom);

    foreach ($xpath->query('//drupal-media[@data-entity-uuid]') as $node) {
      $uuid = $node->attributes?->getNamedItem('data-entity-uuid')?->nodeValue;
      if (!$uuid) {
        continue;
      }
      $media = $this->loadMediaByUuid($uuid);
      if (!$media) {
        continue;
      }
      if (!$media->access('view', $this->currentUser)) {
        continue;
      }
      $file = $this->resolveMediaSourceFile($media);
      if (!$file) {
        continue;
      }
      if (!$file->access('view', $this->currentUser)) {
        continue;
      }
      $key = 'file:' . $file->id();
      if (isset($keys[$key])) {
        continue;
      }
      $keys[$key] = TRUE;
      $candidates[] = [
        'source_type' => 'media',
        'source_id' => (int) $media->id(),
        'source_uuid' => $uuid,
        'file' => $file,
      ];
    }

    foreach ($xpath->query('//img') as $node) {
      $entityType = $node->attributes?->getNamedItem('data-entity-type')?->nodeValue;
      $uuid = $node->attributes?->getNamedItem('data-entity-uuid')?->nodeValue;
      $alt = $node->attributes?->getNamedItem('alt')?->nodeValue ?? '';
      $title = $node->attributes?->getNamedItem('title')?->nodeValue ?? '';
      $src = $node->attributes?->getNamedItem('src')?->nodeValue ?? '';

      $file = NULL;
      $sourceType = 'file';
      $sourceId = NULL;
      $sourceUuid = $uuid;

      if ($entityType === 'media' && $uuid) {
        $media = $this->loadMediaByUuid($uuid);
        if ($media) {
          if (!$media->access('view', $this->currentUser)) {
            continue;
          }
          $file = $this->resolveMediaSourceFile($media);
          if ($file && !$file->access('view', $this->currentUser)) {
            continue;
          }
          $sourceType = 'media';
          $sourceId = (int) $media->id();
        }
      }
      elseif ($uuid) {
        $file = $this->loadFileByUuid($uuid);
        if ($file) {
          if (!$file->access('view', $this->currentUser)) {
            continue;
          }
          $sourceId = (int) $file->id();
        }
      }

      if ($file) {
        $key = 'file:' . $file->id();
        if (isset($keys[$key])) {
          continue;
        }
        $keys[$key] = TRUE;
        $candidates[] = [
          'source_type' => $sourceType,
          'source_id' => $sourceId,
          'source_uuid' => $sourceUuid,
          'source_url' => $src,
          'alt' => $alt,
          'title' => $title,
          'file' => $file,
        ];
        continue;
      }

      if (!$includeExternal || !$this->isAllowedExternalImageUrl($src)) {
        continue;
      }
      $key = 'url:' . $src;
      if (isset($keys[$key])) {
        continue;
      }
      $keys[$key] = TRUE;
      $candidates[] = [
        'source_type' => 'external_url',
        'source_url' => $src,
        'alt' => $alt,
        'title' => $title,
      ];
    }

    return $candidates;
  }

  /**
   * Checks if an external image URL is allowed.
   */
  public function isAllowedExternalImageUrl(string $url): bool {
    if (!$url) {
      return FALSE;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
      return FALSE;
    }
    if (strtolower((string) $parts['scheme']) !== 'https') {
      return FALSE;
    }

    $host = strtolower((string) $parts['host']);
    // parse_url() keeps the surrounding brackets on IPv6 literals (e.g.
    // "[::1]"), which makes FILTER_VALIDATE_IP fail and would otherwise let a
    // loopback/link-local IPv6 address through as if it were a hostname.
    if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
      $host = substr($host, 1, -1);
    }
    if ($host === 'localhost' || str_ends_with($host, '.local')) {
      return FALSE;
    }

    // A literal IP can be range-checked directly.
    if (filter_var($host, FILTER_VALIDATE_IP)) {
      return $this->isPublicIp($host);
    }

    // For a hostname we must resolve it and reject if ANY resolved address is
    // private or reserved; a public-looking hostname that resolves to an
    // internal IP (loopback, RFC1918, cloud metadata endpoint) is the SSRF
    // vector. Fail closed when the host cannot be resolved.
    $ips = $this->resolveHostIps($host);
    if (!$ips) {
      return FALSE;
    }
    foreach ($ips as $ip) {
      if (!$this->isPublicIp($ip)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Checks that an IP address is neither private nor reserved.
   */
  protected function isPublicIp(string $ip): bool {
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
  }

  /**
   * Resolves a hostname to its A/AAAA addresses.
   *
   * @param string $host
   *   The hostname to resolve.
   *
   * @return array<int,string>
   *   Resolved IPv4/IPv6 addresses, empty if the host cannot be resolved.
   */
  protected function resolveHostIps(string $host): array {
    $ips = [];
    $records = @dns_get_record($host, DNS_A | DNS_AAAA);
    if (is_array($records)) {
      foreach ($records as $record) {
        if (!empty($record['ip'])) {
          $ips[] = $record['ip'];
        }
        if (!empty($record['ipv6'])) {
          $ips[] = $record['ipv6'];
        }
      }
    }
    // Fall back to an IPv4-only lookup if DNS_A/DNS_AAAA returned nothing.
    if (!$ips) {
      $ipv4 = @gethostbynamel($host);
      if (is_array($ipv4)) {
        $ips = $ipv4;
      }
    }
    return array_values(array_unique($ips));
  }

  /**
   * Loads media entity by UUID.
   */
  protected function loadMediaByUuid(string $uuid): ?MediaInterface {
    $matches = $this->entityTypeManager->getStorage('media')->loadByProperties(['uuid' => $uuid]);
    $match = reset($matches);
    if (!$match instanceof MediaInterface || !$match->access('view', $this->currentUser)) {
      return NULL;
    }
    return $match;
  }

  /**
   * Loads file entity by UUID.
   */
  protected function loadFileByUuid(string $uuid): ?FileInterface {
    $matches = $this->entityTypeManager->getStorage('file')->loadByProperties(['uuid' => $uuid]);
    $match = reset($matches);
    if (!$match instanceof FileInterface || !$match->access('view', $this->currentUser)) {
      return NULL;
    }
    return $match;
  }

  /**
   * Resolve source file for a media entity.
   */
  protected function resolveMediaSourceFile(MediaInterface $media): ?FileInterface {
    $mediaType = $this->entityTypeManager->getStorage('media_type')->load($media->bundle());
    if (!$mediaType) {
      return NULL;
    }
    $sourceField = $media->getSource()->getSourceFieldDefinition($mediaType)->getName();
    $file = $media->get($sourceField)->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

  /**
   * Writes image metadata JSON to an entity field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $fieldName
   *   Target field name.
   * @param array<int,array<string,mixed>> $metadata
   *   Metadata rows.
   */
  public function storeMetadata(ContentEntityInterface $entity, string $fieldName, array $metadata): void {
    if (!$fieldName || !$entity->hasField($fieldName)) {
      return;
    }
    $json = Json::encode($metadata);
    if (!is_string($json) || $json === '') {
      return;
    }
    $fieldDefinition = $entity->getFieldDefinition($fieldName);
    $fieldType = $fieldDefinition->getType();
    if (in_array($fieldType, ['text', 'text_long', 'text_with_summary'], TRUE)) {
      $entity->set($fieldName, ['value' => $json]);
      return;
    }
    $entity->set($fieldName, $json);
  }

}
