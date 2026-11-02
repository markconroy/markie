<?php

namespace Drupal\Tests\metatag\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Verify that disabled metatag defaults do not load.
 *
 * @group metatag
 */
class DisabledDefaultTagsTest extends BrowserTestBase {

  // Contains helper methods.
  use MetatagHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    // Modules for core functionality.
    'node',
    'taxonomy',
    'user',

    // Need this so that the /node page exists.
    'views',

    // Contrib dependencies.
    'token',

    // This module.
    'metatag',

    // Use the custom route to verify the site works.
    'metatag_test_custom_route',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * A test value for the canonical tag.
   *
   * @var string
   */
  protected $testCanonical = 'https://test.canonical';

  /**
   * A test value for the shortlink tag.
   *
   * @var string
   */
  protected $testShortlink = 'https://test.shortlink';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $config_factory = $this->container->get('config.factory');

    // Set the front page to the main /node page, so that the front page is not
    // just the login page.
    $config_factory->getEditable('system.site')
      ->set('page.front', '/node')
      ->save(TRUE);
  }

  /**
   * Load a default metatag.
   *
   * @param string $id
   *   The id of the metatag default to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The default metatag.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadMetatagDefault($id): EntityInterface|NULL {
    /** @var \Drupal\Core\Entity\EntityStorageInterface $global_metatag_manager */
    $global_metatag_manager = $this->entityTypeManager
      ->getStorage('metatag_defaults');
    return $global_metatag_manager->load($id);
  }

  /**
   * Test that a disabled Frontpage metatag default doesn't load.
   */
  public function testFrontpage() {
    /** @var \Drupal\metatag\Entity\MetatagDefaults $metatag */
    $metatag = $this->loadMetatagDefault('front');
    $metatag->overwriteTags(['canonical_url' => $this->testCanonical]);
    $metatag->save();

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this->testCanonical);

    // Now disable the default. Canonical should then fall back
    // to Global's default, which is page url.
    $metatag->set('status', 0);
    $metatag->save();
    drupal_flush_all_caches();

    $this->drupalGet('<front>');
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    // The page url in Global will be /node's.
    $this_page_url = $this->buildUrl('/node');
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this_page_url);
  }

  /**
   * Test that a disabled 404 metatag default doesn't load.
   */
  public function test404() {
    /** @var \Drupal\metatag\Entity\MetatagDefaults $metatag */
    $metatag = $this->loadMetatagDefault('404');
    $metatag->overwriteTags(['canonical_url' => $this->testCanonical]);
    $metatag->save();

    $this->drupalGet('i-dont-exist');
    $this->assertSession()->statusCodeEquals(404);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this->testCanonical);

    // Now disable the default. Canonical should then fall back
    // to Global's default, which is page url.
    $metatag->set('status', 0);
    $metatag->save();
    drupal_flush_all_caches();

    $this->drupalGet('i-dont-exist');
    $this->assertSession()->statusCodeEquals(404);
    $xpath = $this->xpath("//link[@rel='canonical']");
    // The page url in Global will be /node's.
    $this_page_url = $this->buildUrl('<front>');
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this_page_url . 'i-dont-exist');
  }

  /**
   * Test that a disabled 403 metatag default doesn't load.
   */
  public function test403() {
    /** @var \Drupal\metatag\Entity\MetatagDefaults $metatag */
    $metatag = $this->loadMetatagDefault('403');
    $metatag->overwriteTags(['canonical_url' => $this->testCanonical]);
    $metatag->save();

    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(403);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this->testCanonical);

    // Now disable the default. Canonical should then fall back
    // to Global's default, which is page url.
    $metatag->set('status', 0);
    $metatag->save();
    drupal_flush_all_caches();

    $this->drupalGet('admin/content');
    $this->assertSession()->statusCodeEquals(403);
    $xpath = $this->xpath("//link[@rel='canonical']");
    // The page url in Global will be /node's.
    $this_page_url = $this->buildUrl('/admin/content');
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this_page_url);
  }

  /**
   * Test that a Global metatag doesn't load if its values is set none.
   *
   * Even if it inherits a value from a parent.
   */
  public function testNone() {
    /** @var \Drupal\metatag\Entity\MetatagDefaults $globalMetatag */
    // Set a value for the global "canonical_url" metatag, which the 403
    // metatag inherits from:
    $globalMetatag = $this->loadMetatagDefault('global');
    $globalMetatag->overwriteTags(['canonical_url' => $this->testCanonical]);
    $globalMetatag->save();

    /** @var \Drupal\metatag\Entity\MetatagDefaults $accessDeniedMetatag */
    $accessDeniedMetatag = $this->loadMetatagDefault('403');
    // Now we disable the 403 canonical metatag, meaning, that the metatag
    // should not exist entirely:
    $accessDeniedMetatag->overwriteTags(['canonical_url' => '<none>']);
    $accessDeniedMetatag->save();

    // Check, that the metatag is gone entirely, and we don't have the parent
    // fallback instead:
    $this->drupalGet('/admin');
    $this->assertSession()->statusCodeEquals(403);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEmpty($xpath);
  }

  /**
   * Test that a disabled Node metatag default doesn't load.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityTypeDefaults() {
    $node = $this->createContentTypeNode();
    $this_page_url = $node->toUrl('canonical', ['absolute' => TRUE])
      ->toString();

    // Change the node type default's canonical to a hardcoded test string.
    // Will be inherited by node:page, as normally neither has canonical filled
    // in and inherit it anyway from Global.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $global_metatag_manager */
    $global_metatag_manager = $this->entityTypeManager
      ->getStorage('metatag_defaults');
    /** @var \Drupal\metatag\Entity\MetatagDefaults $entity_metatags */
    $entity_metatags = $global_metatag_manager->load('node');
    $entity_metatags->overwriteTags(['canonical_url' => $this->testCanonical]);
    $entity_metatags->save();

    // Load the node's entity page.
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);

    // Check the meta tags.
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this->testCanonical);

    // Now disable this metatag.
    $entity_metatags->set('status', 0);
    $entity_metatags->save();
    // Clear caches.
    drupal_flush_all_caches();

    // Load the node's entity page.
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);

    // Check the meta tags.
    $xpath = $this->xpath("//link[@rel='canonical']");
    // Should now match global or content one, which is node URL.
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this_page_url);
  }

  /**
   * Test that a disabled node bundle metatag default doesn't load.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testEntityBundleDefaults() {
    $node = $this->createContentTypeNode();
    $this_page_url = $node->toUrl('canonical', ['absolute' => TRUE])
      ->toString();

    // Change the node bundle's default's canonical to a hardcoded test string.
    /** @var \Drupal\Core\Entity\EntityStorageInterface $global_metatag_manager */
    $global_metatag_manager = $this->entityTypeManager
      ->getStorage('metatag_defaults');
    /** @var \Drupal\metatag\Entity\MetatagDefaults $entity_metatags */
    $entity_metatags = $global_metatag_manager->create(['id' => 'node__metatag_test']);
    $entity_metatags->overwriteTags(['canonical_url' => $this->testCanonical]);
    $entity_metatags->save();

    // Load the node's entity page.
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);

    // Check the meta tags.
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this->testCanonical);

    // Now disable this metatag.
    $entity_metatags->set('status', 0);
    $entity_metatags->save();
    // Clear caches.
    drupal_flush_all_caches();

    // Load the node's entity page.
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);

    // Check the meta tags.
    $xpath = $this->xpath("//link[@rel='canonical']");
    // Should now match global or content one, which is node URL.
    $this->assertEquals((string) $xpath[0]->getAttribute('href'), $this_page_url);
  }

  /**
   * Tests setting canonical_url or shortlink to <none> suppresses Core tags.
   */
  public function testEntityCanonicalAndShortlinkNone() {
    $node = $this->createContentTypeNode();
    $this_page_url = $node->toUrl('canonical', ['absolute' => TRUE])
      ->toString();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $global_metatag_manager */
    $global_metatag_manager = $this->entityTypeManager
      ->getStorage('metatag_defaults');
    /** @var \Drupal\metatag\Entity\MetatagDefaults $entity_metatags */
    $entity_metatags = $global_metatag_manager->load('node');

    // 1. Verify that a normal canonical_url value overrides Core canonical.
    $entity_metatags->overwriteTags(['canonical_url' => $this->testCanonical]);
    $entity_metatags->save();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertCount(1, $xpath);
    $this->assertEquals($this->testCanonical, (string) $xpath[0]->getAttribute('href'));

    // 2. Set canonical_url to <none> and verify Core canonical is suppressed
    // while shortlink remains.
    $entity_metatags->overwriteTags(['canonical_url' => '<none>']);
    $entity_metatags->save();
    drupal_flush_all_caches();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEmpty($xpath, 'Canonical link tag is suppressed when set to <none>.');
    $xpath = $this->xpath("//link[@rel='shortlink']");
    $this->assertCount(1, $xpath, 'Core shortlink remains when only canonical is set to <none>.');

    // 3. Set shortlink to a normal value and verify it appears.
    $entity_metatags->overwriteTags([
      'canonical_url' => '<none>',
      'shortlink' => $this->testShortlink,
    ]);
    $entity_metatags->save();
    drupal_flush_all_caches();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEmpty($xpath);
    $xpath = $this->xpath("//link[@rel='shortlink']");
    $this->assertCount(1, $xpath);
    $this->assertEquals($this->testShortlink, (string) $xpath[0]->getAttribute('href'));

    // 4. Set both canonical_url and shortlink to <none> and verify both are
    // suppressed.
    $entity_metatags->overwriteTags([
      'canonical_url' => '<none>',
      'shortlink' => '<none>',
    ]);
    $entity_metatags->save();
    drupal_flush_all_caches();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertEmpty($xpath, 'Canonical link tag is suppressed when set to <none>.');
    $xpath = $this->xpath("//link[@rel='shortlink']");
    $this->assertEmpty($xpath, 'Shortlink link tag is suppressed when set to <none>.');

    // 5. Set canonical_url to a normal value and shortlink to <none>.
    $entity_metatags->overwriteTags([
      'canonical_url' => $this->testCanonical,
      'shortlink' => '<none>',
    ]);
    $entity_metatags->save();
    drupal_flush_all_caches();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertCount(1, $xpath);
    $this->assertEquals($this->testCanonical, (string) $xpath[0]->getAttribute('href'));
    $xpath = $this->xpath("//link[@rel='shortlink']");
    $this->assertEmpty($xpath, 'Shortlink link tag is suppressed when set to <none>.');

    // 6. Set shortlink to <none> with default canonical_url and verify
    // canonical remains while shortlink is suppressed.
    $entity_metatags->overwriteTags(['shortlink' => '<none>']);
    $entity_metatags->save();
    drupal_flush_all_caches();
    $this->drupalGet($this_page_url);
    $this->assertSession()->statusCodeEquals(200);
    $xpath = $this->xpath("//link[@rel='canonical']");
    $this->assertCount(1, $xpath, 'Canonical link tag remains when only shortlink is set to <none>.');
    $this->assertEquals($this->testCanonical, (string) $xpath[0]->getAttribute('href'));
    $xpath = $this->xpath("//link[@rel='shortlink']");
    $this->assertEmpty($xpath, 'Shortlink link tag is suppressed when set to <none>.');
  }

}
