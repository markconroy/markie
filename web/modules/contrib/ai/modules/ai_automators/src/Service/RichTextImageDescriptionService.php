<?php

namespace Drupal\ai_automators\Service;

use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai_automators\Rulehelpers\RichTextImageHelper;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;

/**
 * Builds rich-text image descriptions and metadata.
 */
class RichTextImageDescriptionService {

  /**
   * Constructs the service.
   */
  public function __construct(
    protected RichTextImageHelper $richTextImageHelper,
    protected ClientInterface $httpClient,
    protected LoggerChannelFactoryInterface $loggerFactory,
  ) {
  }

  /**
   * Processes image descriptions from formatted rich-text context.
   *
   * @param string $rawContext
   *   Rich-text HTML.
   * @param bool $includeExternal
   *   Whether secure external images are allowed.
   * @param int $maxImages
   *   Max images to process.
   * @param int $delta
   *   Field delta.
   * @param string $prompt
   *   Prompt used per image.
   * @param array<string,mixed> $metadataContext
   *   Common metadata values merged into each row.
   * @param callable $describeImage
   *   Callback that returns a description for each image.
   *
   * @return array<string,mixed>
   *   Description text, metadata rows, and processing counters.
   */
  public function processRawContext(
    string $rawContext,
    bool $includeExternal,
    int $maxImages,
    int $delta,
    string $prompt,
    array $metadataContext,
    callable $describeImage,
  ): array {
    $summary = $this->richTextImageHelper->extractImageCandidateSummary($rawContext, $includeExternal, $maxImages);
    $candidates = $summary['candidates'] ?? [];
    $encounteredCount = (int) ($summary['encountered_count'] ?? 0);
    $totalCount = (int) ($summary['total_count'] ?? 0);
    $processedCount = (int) ($summary['processed_count'] ?? 0);
    $skippedCount = (int) ($summary['skipped_count'] ?? 0);
    $limitExceeded = !empty($summary['limit_exceeded']) || !empty($summary['has_unprocessed_images']);

    $metadata = [];
    if (empty($candidates)) {
      if ($limitExceeded) {
        $metadata[] = [
          'delta' => $delta,
          'source_type' => 'summary',
          'reason' => 'images_not_fully_processed',
          'encountered_count' => $encounteredCount,
          'eligible_count' => $totalCount,
          'processed_count' => $processedCount,
          'skipped_count' => $skippedCount,
        ];
      }
      return [
        'descriptions' => '',
        'metadata' => $metadata,
        'encountered_count' => $encounteredCount,
        'total_count' => $totalCount,
        'processed_count' => $processedCount,
        'skipped_count' => $skippedCount,
        'limit_exceeded' => $limitExceeded,
      ];
    }

    $lines = [];
    $failedCount = 0;
    foreach ($candidates as $candidate) {
      try {
        $image = $this->buildImageFile($candidate);
        if (!$image) {
          $failedCount++;
          continue;
        }

        $description = trim((string) $describeImage($image, $candidate, $prompt));
        if ($description === '') {
          $failedCount++;
          continue;
        }

        $line = count($lines) + 1;
        $lines[] = $line . '. ' . $description;
        $metadata[] = $this->buildMetadataRow($candidate, $metadataContext, $delta, $description);
      }
      catch (\Throwable $exception) {
        $failedCount++;
        $this->loggerFactory->get('ai_automators')->warning('Skipping image description due to provider error: @message', [
          '@message' => $exception->getMessage(),
        ]);
      }
    }

    if ($failedCount > 0) {
      $processedCount = max(0, $processedCount - $failedCount);
      $skippedCount += $failedCount;
      $limitExceeded = TRUE;
    }

    if ($limitExceeded) {
      $metadata[] = [
        'delta' => $delta,
        'source_type' => 'summary',
        'reason' => 'images_not_fully_processed',
        'encountered_count' => $encounteredCount,
        'eligible_count' => $totalCount,
        'processed_count' => $processedCount,
        'skipped_count' => $skippedCount,
      ];
    }

    return [
      'descriptions' => implode("\n", $lines),
      'metadata' => $metadata,
      'encountered_count' => $encounteredCount,
      'total_count' => $totalCount,
      'processed_count' => $processedCount,
      'skipped_count' => $skippedCount,
      'limit_exceeded' => $limitExceeded,
    ];
  }

  /**
   * Fetches and validates a remote image.
   *
   * @return array{binary: string, mime: string, filename: string}|null
   *   Remote image payload or NULL on any failure.
   */
  public function fetchRemoteImageData(string $url): ?array {
    try {
      $response = $this->httpClient->get($url, [
        'headers' => [
          'User-Agent' => 'Drupal AI Automators image describer (+https://www.drupal.org/project/ai)',
        ],
        'connect_timeout' => 5,
        'timeout' => 15,
        'allow_redirects' => FALSE,
        'http_errors' => FALSE,
      ]);
    }
    catch (\Throwable $exception) {
      $this->loggerFactory->get('ai_automators')->warning('Could not fetch external image @url: @message', [
        '@url' => $url,
        '@message' => $exception->getMessage(),
      ]);
      return NULL;
    }

    if ($response->getStatusCode() !== 200) {
      $this->loggerFactory->get('ai_automators')->warning('External image @url returned status @code.', [
        '@url' => $url,
        '@code' => $response->getStatusCode(),
      ]);
      return NULL;
    }

    $binary = (string) $response->getBody();
    if ($binary === '') {
      return NULL;
    }

    $info = @getimagesizefromstring($binary);
    if ($info === FALSE) {
      $this->loggerFactory->get('ai_automators')->warning('External resource @url is not a valid image.', [
        '@url' => $url,
      ]);
      return NULL;
    }

    $mime = $info['mime'] ?? '';
    $contentType = $response->getHeaderLine('Content-Type');
    if ($contentType !== '' && str_starts_with($contentType, 'image/')) {
      $mime = trim(explode(';', $contentType)[0]);
    }
    if ($mime === '') {
      return NULL;
    }

    $filename = basename((string) parse_url($url, PHP_URL_PATH));
    return [
      'binary' => $binary,
      'mime' => $mime,
      'filename' => $filename !== '' ? $filename : 'image',
    ];
  }

  /**
   * Builds an AI image payload from a candidate.
   */
  protected function buildImageFile(array $candidate): ?ImageFile {
    $image = new ImageFile();
    if (!empty($candidate['file'])) {
      $image->setFileFromFile($candidate['file']);
      return $image;
    }
    if (empty($candidate['source_url'])) {
      return NULL;
    }
    $remote = $this->fetchRemoteImageData($candidate['source_url']);
    if ($remote === NULL) {
      return NULL;
    }
    $image->setBinary($remote['binary']);
    $image->setMimeType($remote['mime']);
    $image->setFilename($remote['filename']);
    return $image;
  }

  /**
   * Builds metadata for a described image.
   *
   * @param array<string,mixed> $candidate
   *   Candidate source details.
   * @param array<string,mixed> $metadataContext
   *   Common metadata values.
   * @param int $delta
   *   Field delta.
   * @param string $description
   *   Generated description.
   *
   * @return array<string,mixed>
   *   Metadata row.
   */
  protected function buildMetadataRow(array $candidate, array $metadataContext, int $delta, string $description): array {
    $metadata = [
      'delta' => $delta,
      'source_type' => $candidate['source_type'] ?? 'unknown',
      'source_id' => $candidate['source_id'] ?? NULL,
      'source_uuid' => $candidate['source_uuid'] ?? NULL,
      'source_url' => $candidate['source_url'] ?? NULL,
      'alt' => $candidate['alt'] ?? '',
      'title' => $candidate['title'] ?? '',
      'description' => $description,
      'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
    ] + $metadataContext;

    if (!empty($candidate['file'])) {
      $metadata['file_id'] = $candidate['file']->id();
      $metadata['file_uuid'] = $candidate['file']->uuid();
      $metadata['mime_type'] = $candidate['file']->getMimeType();
      $uri = $candidate['file']->getFileUri();
      $size = @getimagesize($uri);
      if (is_array($size)) {
        $metadata['width'] = $size[0];
        $metadata['height'] = $size[1];
      }
    }

    return $metadata;
  }

}
