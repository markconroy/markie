<?php

namespace Drupal\Tests\ai_search\Kernel\Update;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ai_search_update_10010() — the exclude_title re-application.
 *
 * Sites upgrading from the ai_search submodule bundled with the AI module
 * 1.4.x are at schema version 10006 and therefore skip the standalone
 * numbering's ai_search_update_10006() which introduced the exclude_title
 * default. This hook re-applies it and is idempotent.
 *
 * @group ai_search
 */
#[RunTestsInSeparateProcesses]
class ExcludeTitleUpdateHookTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'ai', 'search_api', 'ai_search'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Load the install file so ai_search_update_10010() is available.
    include_once dirname(__DIR__, 4) . '/ai_search.install';
  }

  /**
   * Tests that an index without the key gets the FALSE default.
   *
   * @covers ::ai_search_update_10010
   */
  public function testMissingExcludeTitleGetsDefault(): void {
    \Drupal::configFactory()
      ->getEditable('ai_search.index.test_index')
      ->set('index_id', 'test_index')
      ->save();

    ai_search_update_10010();

    $config = \Drupal::config('ai_search.index.test_index');
    $this->assertFalse($config->get('exclude_title'), 'The exclude_title key must be set to FALSE when it was missing.');
    $this->assertSame('test_index', $config->get('index_id'), 'Existing settings must be preserved.');
  }

  /**
   * Tests that an existing exclude_title value is not overwritten.
   *
   * @covers ::ai_search_update_10010
   */
  public function testExistingExcludeTitleIsUntouched(): void {
    \Drupal::configFactory()
      ->getEditable('ai_search.index.test_standalone')
      ->set('exclude_title', TRUE)
      ->save();

    ai_search_update_10010();

    $this->assertTrue(
      \Drupal::config('ai_search.index.test_standalone')->get('exclude_title'),
      'An index that already has exclude_title (e.g. from the standalone module) must not be changed.'
    );
  }

  /**
   * Tests that running the hook twice is idempotent.
   *
   * @covers ::ai_search_update_10010
   */
  public function testHookIsIdempotent(): void {
    \Drupal::configFactory()
      ->getEditable('ai_search.index.test_repeat')
      ->set('index_id', 'test_repeat')
      ->save();

    ai_search_update_10010();
    $first = \Drupal::config('ai_search.index.test_repeat')->getRawData();

    ai_search_update_10010();
    $second = \Drupal::config('ai_search.index.test_repeat')->getRawData();

    $this->assertSame($first, $second, 'Running the hook a second time must not change the configuration.');
  }

  /**
   * Tests that the hook is a no-op without any ai_search index configs.
   *
   * @covers ::ai_search_update_10010
   */
  public function testNoIndexConfigsIsNoOp(): void {
    ai_search_update_10010();
    $this->assertSame([], \Drupal::configFactory()->listAll('ai_search.index.'), 'No configuration may be created by the hook.');
  }

}
