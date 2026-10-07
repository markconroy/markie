<?php

namespace Drupal\Tests\ai\Unit\Base;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Base\AiVdbProviderClientBase;

/**
 * Tests the batch-insert fallback on the VDB provider base class.
 *
 * Providers that do not implement insertBatchIntoCollection() inherit the base
 * implementation, which must insert each record individually.
 *
 * @coversDefaultClass \Drupal\ai\Base\AiVdbProviderClientBase
 * @group ai
 */
class AiVdbProviderClientBaseTest extends UnitTestCase {

  /**
   * Build a base mock with all abstract methods stubbed.
   *
   * Every abstract method is listed in onlyMethods() so the abstract class can
   * be instantiated, while the concrete method under test
   * (insertBatchIntoCollection) keeps its real implementation. The constructor
   * is not invoked.
   */
  protected function provider(): AiVdbProviderClientBase {
    return $this->getMockBuilder(AiVdbProviderClientBase::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getClient',
        'getConfig',
        'ping',
        'isSetup',
        'getCollections',
        'createCollection',
        'dropCollection',
        'insertIntoCollection',
        'deleteFromCollection',
        'querySearch',
        'vectorSearch',
        'getVdbIds',
        'prepareFilters',
      ])
      ->getMock();
  }

  /**
   * The fallback inserts each record in the batch individually, in order.
   *
   * @covers ::insertBatchIntoCollection
   */
  public function testFallbackInsertsEachRecord(): void {
    $provider = $this->provider();

    $batch = [
      ['drupal_long_id' => 'a:0'],
      ['drupal_long_id' => 'a:1'],
      ['drupal_long_id' => 'a:2'],
    ];

    $seen = [];
    $provider->expects($this->exactly(3))
      ->method('insertIntoCollection')
      ->willReturnCallback(function (string $collection_name, array $data, string $database) use (&$seen) {
        $this->assertSame('col', $collection_name);
        $this->assertSame('db', $database);
        $seen[] = $data['drupal_long_id'];
      });

    $provider->insertBatchIntoCollection('col', $batch, 'db');
    $this->assertSame(['a:0', 'a:1', 'a:2'], $seen);
  }

  /**
   * An empty batch performs no inserts.
   *
   * @covers ::insertBatchIntoCollection
   */
  public function testFallbackEmptyBatchDoesNothing(): void {
    $provider = $this->provider();
    $provider->expects($this->never())->method('insertIntoCollection');

    $provider->insertBatchIntoCollection('col', [], 'db');
    $this->addToAssertionCount(1);
  }

}
