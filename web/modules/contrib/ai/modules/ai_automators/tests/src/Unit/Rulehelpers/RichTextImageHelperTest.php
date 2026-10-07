<?php

namespace Drupal\Tests\ai_automators\Unit\Rulehelpers;

use Drupal\ai_automators\Rulehelpers\RichTextImageHelper;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests rich-text image helper behavior.
 *
 * @group ai_automators
 */
class RichTextImageHelperTest extends UnitTestCase {

  /**
   * Builds a helper with hostname resolution stubbed for offline testing.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface|null $entityTypeManager
   *   Optional entity type manager mock.
   * @param \Drupal\Core\Session\AccountProxyInterface|null $currentUser
   *   Optional current user mock.
   *
   * @return \Drupal\ai_automators\Rulehelpers\RichTextImageHelper
   *   Helper whose resolveHostIps() returns fixed addresses per hostname.
   */
  private function helperWithStubbedDns(?EntityTypeManagerInterface $entityTypeManager = NULL, ?AccountProxyInterface $currentUser = NULL): RichTextImageHelper {
    $helper = $this->getMockBuilder(RichTextImageHelper::class)
      ->setConstructorArgs([
        $entityTypeManager ?? $this->createMock(EntityTypeManagerInterface::class),
        $currentUser ?? $this->createMock(AccountProxyInterface::class),
      ])
      ->onlyMethods(['resolveHostIps'])
      ->getMock();
    $helper->method('resolveHostIps')->willReturnCallback(static fn (string $host): array => match ($host) {
      'example.com' => ['93.184.216.34'],
      'internal.example.com' => ['127.0.0.1'],
      'metadata.example.com' => ['169.254.169.254'],
      'rfc1918.example.com' => ['10.0.0.5'],
      default => [],
    });
    return $helper;
  }

  /**
   * Tests external URL allowlist checks.
   */
  public function testAllowedExternalImageUrlValidation(): void {
    $helper = $this->helperWithStubbedDns();

    $this->assertTrue($helper->isAllowedExternalImageUrl('https://example.com/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('http://example.com/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://localhost/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://127.0.0.1/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('/relative/path.jpg'));

    // SSRF: a public-looking hostname that resolves to an internal address.
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://internal.example.com/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://metadata.example.com/image.jpg'));
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://rfc1918.example.com/image.jpg'));
    // Fail closed when a hostname cannot be resolved.
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://unresolvable.example.com/image.jpg'));
    // Bracketed IPv6 loopback literal must not bypass the range check.
    $this->assertFalse($helper->isAllowedExternalImageUrl('https://[::1]/image.jpg'));
  }

  /**
   * Tests extraction of external image URLs from rich text.
   */
  public function testExtractImageCandidatesWithExternalImages(): void {
    $helper = $this->helperWithStubbedDns();
    $html = '<p>Lead</p><img src="https://example.com/a.jpg" alt="a"><img src="https://example.com/a.jpg"><img src="http://example.com/b.jpg">';

    $candidates = $helper->extractImageCandidates($html, TRUE, 5);
    $this->assertCount(1, $candidates);
    $this->assertSame('external_url', $candidates[0]['source_type']);
    $this->assertSame('https://example.com/a.jpg', $candidates[0]['source_url']);

    $blocked = $helper->extractImageCandidates($html, FALSE, 5);
    $this->assertCount(0, $blocked);
  }

  /**
   * Tests picture tags are handled through img fallback.
   */
  public function testPictureTagUsesImgFallback(): void {
    $helper = $this->helperWithStubbedDns();
    $html = '<picture><source srcset="https://example.com/source.webp"><img src="https://example.com/fallback.jpg" alt="fallback"></picture>';

    $candidates = $helper->extractImageCandidates($html, TRUE, 5);
    $this->assertCount(1, $candidates);
    $this->assertSame('https://example.com/fallback.jpg', $candidates[0]['source_url']);
  }

  /**
   * Tests extraction honors max image limit.
   */
  public function testExtractImageCandidatesRespectsLimit(): void {
    $helper = $this->helperWithStubbedDns();
    $html = '<img src="https://example.com/1.jpg"><img src="https://example.com/2.jpg"><img src="https://example.com/3.jpg">';

    $candidates = $helper->extractImageCandidates($html, TRUE, 2);
    $this->assertCount(2, $candidates);
    $this->assertSame('https://example.com/1.jpg', $candidates[0]['source_url']);
    $this->assertSame('https://example.com/2.jpg', $candidates[1]['source_url']);
  }

  /**
   * Tests summary reports skipped images when over max limit.
   */
  public function testExtractImageCandidateSummaryReportsLimitExceeded(): void {
    $helper = $this->helperWithStubbedDns();
    $html = '<img src="https://example.com/1.jpg"><img src="https://example.com/2.jpg"><img src="https://example.com/3.jpg">';

    $summary = $helper->extractImageCandidateSummary($html, TRUE, 2);
    $this->assertSame(3, $summary['total_count']);
    $this->assertSame(2, $summary['processed_count']);
    $this->assertSame(1, $summary['skipped_count']);
    $this->assertTrue($summary['limit_exceeded']);
    $this->assertCount(2, $summary['candidates']);
  }

  /**
   * Tests media candidates are skipped when media access is denied.
   */
  public function testSkipsMediaWithoutViewAccess(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $media = $this->createMock(MediaInterface::class);
    $media->method('access')->with('view', $currentUser)->willReturn(FALSE);

    $mediaStorage = $this->createMock(EntityStorageInterface::class);
    $mediaStorage->method('loadByProperties')->willReturn([$media]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('media')->willReturn($mediaStorage);

    $helper = new RichTextImageHelper($entityTypeManager, $currentUser);
    $html = '<drupal-media data-entity-uuid="22222222-2222-2222-2222-222222222222"></drupal-media>';

    $summary = $helper->extractImageCandidateSummary($html, FALSE, 5);
    $this->assertCount(0, $summary['candidates']);
    $this->assertTrue($summary['has_unprocessed_images']);
  }

  /**
   * Tests file candidates are skipped when file access is denied.
   */
  public function testSkipsFileWithoutViewAccess(): void {
    $currentUser = $this->createMock(AccountProxyInterface::class);
    $file = $this->createMock(FileInterface::class);
    $file->method('access')->with('view', $currentUser)->willReturn(FALSE);

    $fileStorage = $this->createMock(EntityStorageInterface::class);
    $fileStorage->method('loadByProperties')->willReturn([$file]);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('file')->willReturn($fileStorage);

    $helper = new RichTextImageHelper($entityTypeManager, $currentUser);
    $html = '<img data-entity-type="file" data-entity-uuid="33333333-3333-3333-3333-333333333333" src="https://example.com/private.jpg">';

    $summary = $helper->extractImageCandidateSummary($html, FALSE, 5);
    $this->assertCount(0, $summary['candidates']);
    $this->assertTrue($summary['has_unprocessed_images']);
  }

}
