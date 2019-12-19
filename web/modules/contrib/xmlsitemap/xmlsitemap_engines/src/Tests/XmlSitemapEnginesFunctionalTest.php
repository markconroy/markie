<?php

namespace Drupal\xmlsitemap_engines\Tests;

use Drupal\xmlsitemap\Entity\XmlSitemap;
use Drupal\xmlsitemap\Tests\XmlSitemapTestBase;
use Drupal\Core\Url;

/**
 * Test xmlsitemap_engines functionality.
 *
 * @group xmlsitemap
 */
class XmlSitemapEnginesFunctionalTest extends XmlSitemapTestBase {

  /**
   * The path of the custom link.
   *
   * @var string
   *
   * @codingStandardsIgnoreStart
   */
  protected $submit_url;

  /**
   * {@inheritdoc}
   *
   * @codingStandardsIgnoreEnd
   */
  public static $modules = [
    'path',
    'dblog',
    'xmlsitemap_engines',
    'xmlsitemap_engines_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->admin_user = $this->drupalCreateUser(['access content', 'administer xmlsitemap']);
    $this->drupalLogin($this->admin_user);

    // @todo For some reason the test client does not have clean URLs while
    // the test runner does, so it causes mismatches in watchdog assertions
    // later.
    $this->submit_url = Url::fromUri('base://ping', ['absolute' => TRUE, 'query' => ['sitemap' => '']])->toString() . '[sitemap]';
  }

  /**
   * Check if sitemaps are sent to searching engines.
   */
  public function submitEngines() {
    $this->state->setMultiple([
      'xmlsitemap_engines_submit_last' => REQUEST_TIME - 10000,
      'xmlsitemap_generated_last' => REQUEST_TIME - 100,
    ]);
    \Drupal::configFactory()->getEditable('xmlsitemap_engines.settings')->set('minimum_lifetime', 0)->save();
    xmlsitemap_engines_cron();
    $this->assertTrue($this->state->get('xmlsitemap_engines_submit_last') > (REQUEST_TIME - 100), 'Submitted the sitemaps to search engines.');
  }

  /**
   * Check if an url is correctly prepared.
   *
   * @codingStandardsIgnoreStart
   */
  public function testPrepareURL() {
    // @codingStandardsIgnoreEnd
    $sitemap = 'http://example.com/sitemap.xml';
    $input = 'http://example.com/ping?sitemap=[sitemap]&foo=bar';
    $output = 'http://example.com/ping?sitemap=http://example.com/sitemap.xml&foo=bar';
    $this->assertEqual(xmlsitemap_engines_prepare_url($input, $sitemap), $output);
  }

  /**
   * Create sitemaps and send them to search engines.
   */
  public function testSubmitSitemaps() {
    $sitemaps = [];

    $context = [1];
    $sitemap = XmlSitemap::create([
      'id' => xmlsitemap_sitemap_get_context_hash($context),
    ]);
    $sitemap->setContext(serialize($context));
    $sitemap->setLabel('http://example.com');
    $sitemap->save();
    $sitemap->uri = [
      'path' => 'http://example.com/sitemap.xml',
      'options' => [],
    ];
    $sitemaps[] = $sitemap;

    $context = [2];
    $sitemap = XmlSitemap::create([
      'id' => xmlsitemap_sitemap_get_context_hash($context),
    ]);
    $sitemap->setContext(serialize($context));
    $sitemap->setLabel('http://example.com');
    $sitemap->uri = [
      'path' => 'http://example.com/sitemap-2.xml',
      'options' => [],
    ];
    $sitemaps[] = $sitemap;

    xmlsitemap_engines_submit_sitemaps($this->submit_url, $sitemaps);

    $this->assertWatchdogMessage([
      'type' => 'xmlsitemap',
      'message' => 'Received ping for @sitemap.',
      'variables' => [
        '@sitemap' => 'http://example.com/sitemap.xml',
      ],
    ]);
    $this->assertWatchdogMessage([
      'type' => 'xmlsitemap',
      'message' => 'Received ping for @sitemap.',
      'variables' => [
        '@sitemap' => 'http://example.com/sitemap-2.xml',
      ],
    ]);
  }

  /**
   * Check if ping works.
   */
  public function testPing() {
    $edit = ['engines[simpletest]' => TRUE];
    $this->drupalPostForm('admin/config/search/xmlsitemap/engines', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));

    $this->submitEngines();
    $this->assertWatchdogMessage(['type' => 'xmlsitemap', 'message' => 'Submitted the sitemap to %url and received response @code.']);
    $this->assertWatchdogMessage(['type' => 'xmlsitemap', 'message' => 'Received ping for @sitemap.']);
  }

  /**
   * Check if custom urls are functional.
   *
   * @codingStandardsIgnoreStart
   */
  public function testCustomURL() {
    // @codingStandardsIgnoreEnd
    $edit = ['custom_urls' => 'an-invalid-url'];
    $this->drupalPostForm('admin/config/search/xmlsitemap/engines', $edit, t('Save configuration'));
    $this->assertText('Invalid URL an-invalid-url.');
    $this->assertNoText('The configuration options have been saved.');

    $url = Url::fromUri('base://ping', ['absolute' => TRUE])->toString();
    $edit = ['custom_urls' => $url];
    $this->drupalPostForm('admin/config/search/xmlsitemap/engines', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));

    $edit = ['custom_urls' => $this->submit_url];
    $this->drupalPostForm('admin/config/search/xmlsitemap/engines', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));

    $this->submitEngines();
    $url = xmlsitemap_engines_prepare_url($this->submit_url, Url::fromRoute('xmlsitemap.sitemap_xml', [], ['absolute' => TRUE])->toString());
    $this->assertWatchdogMessage([
      'type' => 'xmlsitemap',
      'message' => 'Submitted the sitemap to %url and received response @code.',
      'variables' => [
        '%url' => $url,
        '@code' => 200,
      ],
    ]);
    $this->assertWatchdogMessage([
      'type' => 'xmlsitemap',
      'message' => 'Received ping for @sitemap.',
      'variables' => [
        '@sitemap' => Url::fromRoute('xmlsitemap.sitemap_xml', [], [
          'absolute' => TRUE,
        ])->toString(),
      ],
    ]);
  }

}
