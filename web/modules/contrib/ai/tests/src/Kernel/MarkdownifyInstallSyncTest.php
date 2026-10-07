<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel;

use Drupal\ai\Service\HtmlToMarkdown\HtmlToMarkdownConverter;
use Drupal\ai\Service\HtmlToMarkdown\MarkdownifyHtmlToMarkdownAdapter;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that installing markdownify copies over the AI converter settings.
 *
 * @see ai_modules_installed()
 * @group ai
 */
#[RunTestsInSeparateProcesses]
final class MarkdownifyInstallSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai', 'key'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (!isset(\Drupal::service('extension.list.module')->getList()['markdownify'])) {
      $this->markTestSkipped('The markdownify module is not present in this codebase.');
    }
    $this->installConfig(['ai']);
  }

  /**
   * Installing markdownify copies the active AI settings.
   *
   * The league plugin config should have the same config, so conversion
   * behavior does not change once ai.html_to_markdown_converter starts
   * delegating to markdownify.
   */
  public function testSettingsAreCopiedWhenMarkdownifyIsInstalled(): void {
    $this->config('ai.html_to_markdown.settings')
      ->set('header_style', 'setext')
      ->set('strip_tags', FALSE)
      ->set('strip_placeholder_links', TRUE)
      ->set('list_item_style', '+')
      ->save();

    // Before markdownify is installed the default league-based converter is
    // used.
    $this->assertInstanceOf(
      HtmlToMarkdownConverter::class,
      $this->container->get('ai.html_to_markdown_converter'),
    );

    \Drupal::service('module_installer')->install(['markdownify']);

    $league_settings = $this->config('markdownify.settings')->get('converters.league');
    $this->assertSame('setext', $league_settings['header_style']);
    $this->assertFalse($league_settings['strip_tags']);
    $this->assertTrue($league_settings['strip_placeholder_links']);
    $this->assertSame('+', $league_settings['list_item_style']);

    // Installing markdownify must activate the adapter via AiServiceProvider,
    // so ai.html_to_markdown_converter now delegates to markdownify. A unit
    // test cannot catch a typo in the service provider class name; this
    // assertion exercises the real container rebuild.
    $this->assertInstanceOf(
      MarkdownifyHtmlToMarkdownAdapter::class,
      \Drupal::service('ai.html_to_markdown_converter'),
    );
  }

  /**
   * Installing an unrelated module must not touch markdownify settings.
   */
  public function testUnrelatedModuleInstallDoesNotTriggerSync(): void {
    $this->config('ai.html_to_markdown.settings')
      ->set('header_style', 'setext')
      ->save();

    \Drupal::service('module_installer')->install(['help']);

    $this->assertNull(\Drupal::config('markdownify.settings')->get('converters.league'));
  }

}
