<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Translate consumer type appears and push stays off.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
final class AiContextConsumerTypeTranslateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'block',
    'field',
    'file',
    'text',
    'filter',
    'options',
    'taxonomy',
    'key',
    'language',
    'content_translation',
    'content_moderation',
    'workflows',
    'views',
    'ai',
    'ai_agents',
    'ai_context',
    'ai_translate',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the Translate type appears on the consumer types listing.
   */
  public function testTypeAppearsOnConsumerTypesListing(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer ai context',
    ]));

    $this->drupalGet(Url::fromRoute('ai_context.settings.consumer_types'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementExists(
      'xpath',
      '//tr[.//strong[normalize-space()="Translate"]][contains(., "Enabled")]',
    );
  }

  /**
   * Tests the instance is listed and push stays off until configured.
   */
  public function testConsumersListingPushStaysOff(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer ai context',
    ]));

    $this->drupalGet(Url::fromRoute('ai_context.settings.consumers'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('AI Translate');
    $this->assertSession()->pageTextContains('Off');
    $this->assertSession()->pageTextNotContains('Off (type unavailable)');
    $this->assertSession()->linkByHrefExists(
      Url::fromRoute('ai_context.settings.consumer_edit', [
        'consumer_id' => 'translate:default',
      ])->toString(),
    );
    $this->assertSession()->linkByHrefNotExists(
      Url::fromRoute('ai_translate.settings_form')->toString(),
    );
    $this->assertSession()->elementNotExists(
      'xpath',
      '//a[starts-with(normalize-space(), "AI Translate")]',
    );
  }

  /**
   * Tests the instance name links to settings when permitted.
   */
  public function testConsumerLabelLinksToSettings(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'administer ai context',
      'manage ai translation prompts',
    ]));

    $this->drupalGet(Url::fromRoute('ai_context.settings.consumers'));
    $this->assertSession()->statusCodeEquals(200);
    $href = Url::fromRoute('ai_translate.settings_form')->toString();
    // CCC appends visually hidden "(opens in new tab)" inside the
    // anchor, so normalize-space() is not exactly "AI Translate".
    $this->assertSession()->elementExists(
      'xpath',
      '//a[@href="' . $href . '"][starts-with(normalize-space(), "AI Translate")]',
    );
  }

  /**
   * Translate settings expose push state and an accessible CCC link.
   */
  public function testTranslateSettingsShowPushStateAndConfigurationLink(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'manage ai translation prompts',
      'administer ai context',
    ]));
    $this->drupalGet(Url::fromRoute('ai_translate.settings_form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Automatic context push is off for translations.');
    $this->assertSession()->linkByHrefExists(Url::fromRoute('ai_context.settings.consumer_edit', [
      'consumer_id' => 'translate:default',
    ])->toString());

    $this->config('ai_context.consumers')->set('consumers', [[
      'consumer_id' => 'translate:default',
      'type' => 'translate',
      'push_enabled' => TRUE,
    ],
    ])->save();
    $this->drupalGet(Url::fromRoute('ai_translate.settings_form'));
    $this->assertSession()->pageTextContains('Automatic context push is enabled for chat-proxy translations.');
  }

  /**
   * Translators without CCC administration see state without an unusable link.
   */
  public function testTranslateSettingsHideConfigurationLinkWithoutPermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['manage ai translation prompts']));
    $this->drupalGet(Url::fromRoute('ai_translate.settings_form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Automatic context push is off for translations.');
    $this->assertSession()->linkByHrefNotExists(Url::fromRoute('ai_context.settings.consumer_edit', [
      'consumer_id' => 'translate:default',
    ])->toString());
  }

}
