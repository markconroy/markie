<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests readable model descriptions on the translation overview.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class TranslationModelLabelTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'ai_translate', 'ai_translate_test'];

  /**
   * The untranslated source node.
   */
  protected NodeInterface $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    $this->drupalCreateContentType(['type' => 'article']);
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);
    $this->node = $this->drupalCreateNode(['type' => 'article', 'langcode' => 'en']);
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * The setting defaults off and controls descriptions after saving the form.
   */
  public function testModelLabelSetting(): void {
    $this->config('ai.settings')->set('default_providers.translate_text', [
      'provider_id' => 'ai_translate_counting',
      'model_id' => 'counter-1',
    ])->save();
    $this->assertFalse($this->config('ai_translate.settings')->get('show_model_name'));
    // Existing sites without the new key also keep model names hidden.
    $this->config('ai_translate.settings')->clear('show_model_name')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkExists('Translate with AI');
    $this->assertSession()->pageTextNotContains('Model:');

    $this->drupalGet('/admin/config/ai/ai-translate');
    $this->assertSession()->checkboxNotChecked('show_model_name');
    $this->submitForm(['show_model_name' => TRUE], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertTrue($this->config('ai_translate.settings')->get('show_model_name'));
    $this->assertSession()->checkboxChecked('show_model_name');
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: Counter one');

    $this->drupalGet('/admin/config/ai/ai-translate');
    $this->submitForm(['show_model_name' => FALSE], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->assertFalse($this->config('ai_translate.settings')->get('show_model_name'));
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkExists('Translate with AI');
    $this->assertSession()->pageTextNotContains('Model:');
  }

  /**
   * The overview gets a cache lifetime only when it shows model names.
   */
  public function testCacheLifetime(): void {
    $this->setContainerParameter('http.response.debug_cacheability_headers', TRUE);
    $this->rebuildContainer();
    $this->resetAll();
    $this->config('ai.settings')->set('default_providers.translate_text', [
      'provider_id' => 'ai_translate_counting',
      'model_id' => 'counter-1',
    ])->save();

    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->linkExists('Translate with AI');
    $this->assertSession()->responseHeaderMatches('X-Drupal-Cache-Max-Age', '/^-1 /');

    $this->config('ai_translate.settings')->set('show_model_name', TRUE)->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: Counter one');
    $this->assertSession()->responseHeaderMatches('X-Drupal-Cache-Max-Age', '/^3600$/');
  }

  /**
   * Uses native provider labels and refreshes them when configuration changes.
   */
  public function testNativeModelLabels(): void {
    $this->config('ai_translate.settings')->set('show_model_name', TRUE)->save();
    $this->config('ai.settings')->set('default_providers.translate_text', [
      'provider_id' => 'ai_translate_counting',
      'model_id' => 'counter-1',
    ])->save();
    // Native providers do not use the chat-only language override.
    $this->config('ai_translate.settings')
      ->set('language_settings.fr.model', 'ai_translate_counting_chat__chat-2')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Translate with AI');
    $this->assertSession()->pageTextContains('Model: Counter one');
    $this->assertSession()->pageTextNotContains('counter-1');
    $this->assertSession()->pageTextNotContains('Chat two');

    $this->config('ai.settings')
      ->set('models.ai_translate_counting.translate_text.counter-1.label', 'Custom <em>model</em> & name')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: Custom <em>model</em> & name');
    $this->assertSession()->elementNotExists('css', '.description em');
    $this->assertSession()->pageTextNotContains('Model: Counter one');

    // A label that PHP reads as empty is still a label.
    $this->config('ai.settings')
      ->set('default_providers.translate_text.model_id', 'counter-0')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: 0');
  }

  /**
   * Resolves language overrides and stale chat models like translation does.
   */
  public function testChatModelLabels(): void {
    $this->config('ai_translate.settings')->set('show_model_name', TRUE)->save();
    $this->config('ai.settings')->set('default_providers', [
      'translate_text' => ['provider_id' => 'chat_translation', 'model_id' => 'chat-2'],
      'chat' => ['provider_id' => 'ai_translate_counting_chat', 'model_id' => 'chat-1'],
    ])->save();
    $this->config('ai_translate.settings')
      ->set('language_settings.fr.model', 'ai_translate_second_chat__second-1')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: Second one');
    $this->assertSession()->pageTextContains('Model: Chat two');
    $this->assertSession()->pageTextNotContains('chat_translation');

    $this->config('ai_translate.settings')
      ->set('language_settings.fr.model', 'ai_translate_second_chat__withdrawn')->save();
    $this->config('ai.settings')
      ->set('default_providers.translate_text.model_id', 'withdrawn')->save();
    $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
    $this->assertSession()->pageTextContains('Model: Chat one');
    $this->assertSession()->pageTextNotContains('Model: Second one');
    $this->assertSession()->pageTextNotContains('withdrawn');
  }

  /**
   * Missing labels and provider failures leave the action usable.
   */
  public function testUnavailableModelLabels(): void {
    $this->config('ai_translate.settings')->set('show_model_name', TRUE)->save();
    foreach (['ai_translate_counting', 'missing_provider'] as $provider) {
      $this->config('ai.settings')->set('default_providers.translate_text', [
        'provider_id' => $provider,
        'model_id' => 'unknown',
      ])->save();
      $this->drupalGet($this->node->toUrl('drupal:content-translation-overview'));
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->linkExists('Translate with AI');
      $this->assertSession()->pageTextNotContains('Model:');
      $this->assertSession()->pageTextNotContains('unknown');
    }
  }

}
