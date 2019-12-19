<?php

namespace Drupal\xmlsitemap\Tests;

use Drupal\Core\Language\LanguageInterface;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests the generation of taxonomy links.
 *
 * @group xmlsitemap
 */
class XmlSitemapTaxonomyFunctionalTest extends XmlSitemapTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Add a vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'description' => $this->randomMachineName(),
      'vid' => 'tags',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'help' => '',
    ]);
    $vocabulary->save();

    $this->admin_user = $this->drupalCreateUser(['administer taxonomy', 'administer xmlsitemap']);
  }

  /**
   * Test xmlsitemap settings for taxonomies.
   */
  public function testTaxonomySettings() {
    $this->drupalLogin($this->admin_user);

    // Enable XML Sitemap settings for our vocabulary.
    $settings = [
      'status' => '1',
      'priority' => '1.0',
    ];
    xmlsitemap_link_bundle_settings_save('taxonomy_term', 'tags', $settings);

    $this->drupalGet('admin/structure/taxonomy/manage/tags/add');
    $this->assertResponse(200);
    $this->assertField('xmlsitemap[status]');
    $this->assertField('xmlsitemap[priority]');
    $this->assertField('xmlsitemap[changefreq]');

    $term_name = $this->randomMachineName();
    $edit = [
      'name[0][value]' => $term_name,
      'xmlsitemap[status]' => 'default',
      'xmlsitemap[priority]' => 'default',
    ];
    $this->drupalPostForm(NULL, $edit, t('Save'));

    $term = taxonomy_term_load_multiple_by_name($term_name, 'tags')[1];
    $link = \Drupal::service('xmlsitemap.link_storage')->load('taxonomy_term', $term->id());
    $this->assertEqual((int) $link['status'], 1);
    $this->assertEqual((int) $link['priority'], 1);
  }

}
