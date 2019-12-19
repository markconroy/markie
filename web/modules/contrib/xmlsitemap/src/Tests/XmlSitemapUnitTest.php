<?php

namespace Drupal\xmlsitemap\Tests;

/**
 * Unit tests for the XML sitemap module.
 *
 * @group xmlsitemap
 */
class XmlSitemapUnitTest extends XmlSitemapTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->admin_user = $this->drupalCreateUser([
      'access content',
      'administer site configuration',
      'administer xmlsitemap',
    ]);
  }

  /**
   * Tests for xmlsitemap flags.
   */
  public function testAssertFlag() {
    $this->state->set('xmlsitemap_rebuild_needed', TRUE);
    $this->assertTrue(xmlsitemap_var('xmlsitemap_rebuild_needed'));
    $this->assertTrue($this->assertFlag('xmlsitemap_rebuild_needed', TRUE, FALSE));
    $this->assertTrue(xmlsitemap_var('xmlsitemap_rebuild_needed'));
    $this->assertTrue($this->assertFlag('xmlsitemap_rebuild_needed', TRUE, TRUE));
    $this->assertFalse(xmlsitemap_var('xmlsitemap_rebuild_needed'));
    $this->assertTrue($this->assertFlag('xmlsitemap_rebuild_needed', FALSE, FALSE));
    $this->assertFalse(xmlsitemap_var('xmlsitemap_rebuild_needed'));
  }

  /**
   * Tests for xmlsitemap_get_changefreq().
   */
  public function testGetChangefreq() {
    // The test values.
    $values = [
      0,
      mt_rand(1, XMLSITEMAP_FREQUENCY_ALWAYS),
      mt_rand(XMLSITEMAP_FREQUENCY_ALWAYS + 1, XMLSITEMAP_FREQUENCY_HOURLY),
      mt_rand(XMLSITEMAP_FREQUENCY_HOURLY + 1, XMLSITEMAP_FREQUENCY_DAILY),
      mt_rand(XMLSITEMAP_FREQUENCY_DAILY + 1, XMLSITEMAP_FREQUENCY_WEEKLY),
      mt_rand(XMLSITEMAP_FREQUENCY_WEEKLY + 1, XMLSITEMAP_FREQUENCY_MONTHLY),
      mt_rand(XMLSITEMAP_FREQUENCY_MONTHLY + 1, XMLSITEMAP_FREQUENCY_YEARLY),
      mt_rand(XMLSITEMAP_FREQUENCY_YEARLY + 1, mt_getrandmax()),
    ];

    // The expected values.
    $expected = [
      FALSE,
      'always',
      'hourly',
      'daily',
      'weekly',
      'monthly',
      'yearly',
      'never',
    ];

    foreach ($values as $i => $value) {
      $actual = xmlsitemap_get_changefreq($value);
      $this->assertIdentical($actual, $expected[$i]);
    }
  }

  /**
   * Tests for xmlsitemap_get_chunk_count().
   */
  public function testGetChunkCount() {
    // Set a low chunk size for testing.
    $this->config->set('chunk_size', 4)->save();
    $database = \Drupal::database();

    // Make the total number of links just equal to the chunk size.
    $count = $database->query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField();
    for ($i = $count; $i < 4; $i++) {
      $this->addSitemapLink();
      $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
    }
    $this->assertEqual($database->query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField(), 4);

    // Add a disabled link, should not change the chunk count.
    $this->addSitemapLink(['status' => FALSE]);
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);

    // Add a visible link, should finally bump up the chunk count.
    $this->addSitemapLink();
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 2);

    // Change all links to disabled. The chunk count should be 1 not 0.
    $database->query("UPDATE {xmlsitemap} SET status = 0");
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
    $this->assertEqual(xmlsitemap_get_link_count(), 0);

    // Delete all links. The chunk count should be 1 not 0.
    $database->query("DELETE FROM {xmlsitemap}");
    $this->assertEqual($database->query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField(), 0);
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
  }

  /**
   * Tests for xmlsitemap_calculate_changereq().
   */
  public function testCalculateChangefreq() {
    // The test values.
    $values = [
      [],
      [REQUEST_TIME],
      [REQUEST_TIME, REQUEST_TIME - 200],
      [REQUEST_TIME - 200, REQUEST_TIME, REQUEST_TIME - 600],
    ];

    // Expected values.
    $expected = [0, 0, 200, 300];

    foreach ($values as $i => $value) {
      $actual = xmlsitemap_calculate_changefreq($value);
      $this->assertEqual($actual, $expected[$i]);
    }
  }

  /**
   * Test for xmlsitemap_recalculate_changefreq().
   */
  public function testRecalculateChangefreq() {
    // The starting test value.
    $value = [
      'lastmod' => REQUEST_TIME - 1000,
      'changefreq' => 0,
      'changecount' => 0,
    ];

    // Expected values.
    $expecteds = [
      ['lastmod' => REQUEST_TIME, 'changefreq' => 1000, 'changecount' => 1],
      ['lastmod' => REQUEST_TIME, 'changefreq' => 500, 'changecount' => 2],
      ['lastmod' => REQUEST_TIME, 'changefreq' => 333, 'changecount' => 3],
    ];

    foreach ($expecteds as $expected) {
      xmlsitemap_recalculate_changefreq($value);
      $this->assertEqual($value, $expected);
    }
  }

  /**
   * Tests for XmlSitemapLinkStorage::save().
   */
  public function testSaveLink() {
    $link = [
      'type' => 'testing',
      'subtype' => '',
      'id' => 1,
      'loc' => '/testing',
      'status' => 1,
    ];
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['status'] = 0;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    $link['loc'] = '/new_location';
    $link['status'] = 1;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 0.0;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 0.1;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 1.0;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 1;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', FALSE);

    $link['priority'] = 0;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    $link['priority_override'] = 0;
    $link['status'] = 1;
    $this->linkStorage->save($link);
    $this->assertFlag('xmlsitemap_regenerate_needed', FALSE);
  }

  /**
   * Tests for XmlSitemapLinkStorage::delete().
   */
  public function testLinkDelete() {
    // Add our testing data.
    $link1 = $this->addSitemapLink(['loc' => '/testing1', 'status' => 0]);
    $link2 = $this->addSitemapLink(['loc' => '/testing1', 'status' => 1]);
    $link3 = $this->addSitemapLink(['status' => 0]);
    $this->state->set('xmlsitemap_regenerate_needed', FALSE);

    // Test delete multiple links.
    // Test that the regenerate flag is set when visible links are deleted.
    $deleted = $this->linkStorage->deleteMultiple(['loc' => '/testing1']);
    $this->assertEqual($deleted, 2);
    $this->assertFalse($this->linkStorage->load($link1['type'], $link1['id']));
    $this->assertFalse($this->linkStorage->load($link2['type'], $link2['id']));
    $this->assertTrue($this->linkStorage->load($link3['type'], $link3['id']));
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);

    $deleted = $this->linkStorage->delete($link3['type'], $link3['id']);
    $this->assertEqual($deleted, 1);
    $this->assertFalse($this->linkStorage->load($link3['type'], $link3['id']));
    $this->assertFlag('xmlsitemap_regenerate_needed', FALSE);
  }

  /**
   * TestUpdateLinks.
   *
   * Tests for
   * \Drupal\xmlsitemap\XmlSitemapLinkStorageInterface::updateMultiple().
   */
  public function testUpdateLinks() {
    // Add our testing data.
    // @codingStandardsIgnoreLine
    $links = [];
    $links[1] = $this->addSitemapLink(['subtype' => 'group1']);
    $links[2] = $this->addSitemapLink(['subtype' => 'group1']);
    $links[3] = $this->addSitemapLink(['subtype' => 'group2']);
    $this->state->set('xmlsitemap_regenerate_needed', FALSE);
    // Id | type    | subtype | language | access | status | priority
    // 1  | testing | group1  | ''       | 1      | 1      | 0.5
    // 2  | testing | group1  | ''       | 1      | 1      | 0.5
    // 3  | testing | group2  | ''       | 1      | 1      | 0.5.
    $updated = $this->linkStorage->updateMultiple(['status' => 0], [
      'type' => 'testing',
      'subtype' => 'group1',
      'status_override' => 0,
    ]);
    $this->assertEqual($updated, 2);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);
    // Id | type    | subtype | language | status | priority
    // 1  | testing | group1  | ''       | 0      | 0.5
    // 2  | testing | group1  | ''       | 0      | 0.5
    // 3  | testing | group2  | ''       | 1      | 0.5.
    $updated = $this->linkStorage->updateMultiple(['priority' => 0.0], [
      'type' => 'testing',
      'subtype' => 'group1',
      'priority_override' => 0,
    ]);
    $this->assertEqual($updated, 2);
    $this->assertFlag('xmlsitemap_regenerate_needed', FALSE);
    // Id | type    | subtype | language | status | priority
    // 1  | testing | group1  | ''       | 0      | 0.0
    // 2  | testing | group1  | ''       | 0      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5.
    $updated = $this->linkStorage->updateMultiple(['subtype' => 'group2'], ['type' => 'testing', 'subtype' => 'group1']);
    $this->assertEqual($updated, 2);
    $this->assertFlag('xmlsitemap_regenerate_needed', FALSE);
    // Id | type    | subtype | language | status | priority
    // 1  | testing | group2  | ''       | 0      | 0.0
    // 2  | testing | group2  | ''       | 0      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5.
    $updated = $this->linkStorage->updateMultiple(['status' => 1], [
      'type' => 'testing',
      'subtype' => 'group2',
      'status_override' => 0,
      'status' => 0,
    ]);
    $this->assertEqual($updated, 2);
    $this->assertFlag('xmlsitemap_regenerate_needed', TRUE);
    // Id | type    | subtype | language | status | priority
    // 1  | testing | group2  | ''       | 1      | 0.0
    // 2  | testing | group2  | ''       | 1      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5.
  }

  /**
   * Test that duplicate paths are skipped during generation.
   */
  public function testDuplicatePaths() {
    $this->drupalLogin($this->admin_user);
    // @codingStandardsIgnoreStart
    $link1 = $this->addSitemapLink(['loc' => '/duplicate']);
    $link2 = $this->addSitemapLink(['loc' => '/duplicate']);
    // @codingStandardsIgnoreEnd
    $this->regenerateSitemap();
    $this->drupalGetSitemap();
    $this->assertUniqueText('duplicate');
  }

  /**
   * Test that the sitemap will not be genereated before the lifetime expires.
   */
  public function testMinimumLifetime() {
    $this->drupalLogin($this->admin_user);
    $this->config->set('minimum_lifetime', 300)->save();
    $this->regenerateSitemap();

    $link = $this->addSitemapLink(['loc' => '/lifetime-test']);
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertResponse(200);
    $this->assertNoRaw('lifetime-test');

    $this->state->set('xmlsitemap_generated_last', REQUEST_TIME - 400);
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertRaw('lifetime-test');

    $this->linkStorage->delete($link['type'], $link['id']);
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertRaw('lifetime-test');

    $this->regenerateSitemap();
    $this->drupalGetSitemap();
    $this->assertResponse(200);
    $this->assertNoRaw('lifetime-test');
  }

}
