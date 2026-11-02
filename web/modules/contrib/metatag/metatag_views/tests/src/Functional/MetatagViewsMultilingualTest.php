<?php

namespace Drupal\Tests\metatag_views\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\metatag\Functional\MetatagHelperTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Views metatags use current language, not config entity language.
 *
 * Regression test for https://www.drupal.org/node/3302453
 *
 * When viewing a View page in a non-default language, the canonical URL
 * should reflect the current language URL, not the default language URL.
 *
 * @group metatag
 */
#[Group('metatag')]
class MetatagViewsMultilingualTest extends BrowserTestBase {

  use MetatagHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Modules for core functionality and content.
    'block',
    'field',
    'node',
    'user',

    // Multilingual functionality.
    'language',
    'locale',
    'content_translation',

    // Views core and UI.
    'views',
    'views_ui',

    // Contrib dependencies.
    'token',
    'metatag',

    // This module.
    'metatag_views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add Italian language.
    ConfigurableLanguage::createFromLangcode('it')->save();

    // Configure URL-based language detection with path prefix.
    $this->config('language.negotiation')
      ->set('url.source', 'path_prefix')
      ->set('url.prefixes', [
        'en' => '',
        'it' => 'it',
      ])
      ->save();

    $this->config('language.types')
      ->set('configurable', ['language_interface'])
      ->set('negotiation.language_interface.enabled', [
        'language-url' => 0,
        'language-selected' => 1,
      ])
      ->save();

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests canonical URL uses current language for Views pages.
   */
  public function testViewsCanonicalUrlUsesCurrentLanguage(): void {
    $this->loginUser1();

    // Create a view via the admin UI.
    $this->drupalGet('/admin/structure/views/add');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'label' => 'Test Articles',
      'id' => 'test_articles',
      'page[create]' => 1,
      'page[path]' => 'articles',
    ];
    $this->submitForm($edit, 'Save and edit');

    // Configure metatags for the view.
    $this->drupalGet('/admin/structure/views/nojs/display/test_articles/page_1/metatags');
    $this->assertSession()->statusCodeEquals(200);
    $edit = [
      'canonical_url' => '[current-page:url]',
    ];
    $this->submitForm($edit, 'Apply');
    $this->drupalGet('/admin/structure/views/view/test_articles');
    $this->submitForm([], 'Save');

    // Test English (default language): canonical should NOT contain /it/.
    $this->drupalGet('articles');
    $this->assertSession()->statusCodeEquals(200);
    $canonical_en = $this->assertSession()
      ->elementExists('xpath', '//link[@rel="canonical"]')
      ->getAttribute('href');
    $this->assertStringContainsString('/articles', $canonical_en);
    $this->assertStringNotContainsString('/it/', $canonical_en);

    // Test Italian: canonical MUST contain /it/articles.
    $this->drupalGet('it/articles');
    $this->assertSession()->statusCodeEquals(200);
    $canonical_it = $this->assertSession()
      ->elementExists('xpath', '//link[@rel="canonical"]')
      ->getAttribute('href');
    $this->assertStringContainsString('/it/articles', $canonical_it,
      'Canonical URL should use current language (/it/articles), not default language (/articles).');
  }

}
