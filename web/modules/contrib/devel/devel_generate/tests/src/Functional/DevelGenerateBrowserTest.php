<?php

namespace Drupal\Tests\devel_generate\Functional;

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\devel_generate\Traits\DevelGenerateSetupTrait;

/**
 * Tests the logic to generate data.
 *
 * @group devel_generate
 */
class DevelGenerateBrowserTest extends BrowserTestBase {

  use DevelGenerateSetupTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('menu_ui', 'node', 'comment', 'taxonomy', 'path', 'devel_generate');

  /**
   * Prepares the testing environment
   */
  public function setUp() {
    parent::setUp();

    $this->setUpData();

    $admin_user = $this->drupalCreateUser(array('administer devel_generate'));
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests generate commands
   */
  public function testDevelGenerate() {
    // Creating users.
    $edit = array(
      'num' => 4,
    );
    $this->drupalPostForm('admin/config/development/generate/user', $edit, t('Generate'));
    $this->assertText(t('4 users created.'));
    $this->assertText(t('Generate process complete.'));

    // Tests that if no content types are selected an error message is shown.
    $edit = array(
      'num' => 4,
      'title_length' => 4,
    );
    $this->drupalPostForm('admin/config/development/generate/content', $edit, t('Generate'));
    $this->assertText(t('Please select at least one content type'));

    // Creating content.
    // First we create a node in order to test the Delete content checkbox.
    $this->drupalCreateNode(array('type' => 'article'));

    $edit = array(
      'num' => 4,
      'kill' => TRUE,
      'node_types[article]' => TRUE,
      'time_range' => 604800,
      'max_comments' => 3,
      'title_length' => 4,
      'add_alias' => 1,
    );
    $this->drupalPostForm('admin/config/development/generate/content', $edit, t('Generate'));
    $this->assertSession()->pageTextContains(t('Deleted 1 nodes.'));
    $this->assertSession()->pageTextContains(t('Finished creating 4 nodes'));
    $this->assertSession()->pageTextContains(t('Generate process complete.'));

    // Tests that nodes have been created in the generation process.
    $nodes = Node::loadMultiple();
    $this->assert(count($nodes) == 4, 'Nodes generated successfully.');

    // Tests url alias for the generated nodes.
    foreach ($nodes as $node) {
      $alias = 'node-' . $node->id() . '-' . $node->bundle();
      $this->drupalGet($alias);
      $this->assertSession()->statusCodeEquals('200');
      $this->assertSession()->pageTextContains($node->getTitle(), 'Generated url alias for the node works.');
    }

    // Creating terms.
    $edit = array(
      'vids[]' => $this->vocabulary->id(),
      'num' => 5,
      'title_length' => 12,
    );
    $this->drupalPostForm('admin/config/development/generate/term', $edit, t('Generate'));
    $this->assertSession()->pageTextContains(t('Created the following new terms: '));
    $this->assertSession()->pageTextContains(t('Generate process complete.'));

    // Creating vocabularies.
    $edit = array(
      'num' => 5,
      'title_length' => 12,
      'kill' => TRUE,
    );
    $this->drupalPostForm('admin/config/development/generate/vocabs', $edit, t('Generate'));
    $this->assertSession()->pageTextContains(t('Created the following new vocabularies: '));
    $this->assertSession()->pageTextContains(t('Generate process complete.'));

    // Creating menus.
    $edit = array(
      'num_menus' => 5,
      'num_links' => 7,
      'title_length' => 12,
      'link_types[node]' => 1,
      'link_types[front]' => 1,
      'link_types[external]' => 1,
      'max_depth' => 4,
      'max_width' => 6,
      'kill' => 1,
    );
    $this->drupalPostForm('admin/config/development/generate/menu', $edit, t('Generate'));
    $this->assertSession()->pageTextContains(t('Created the following new menus: '));
    $this->assertSession()->pageTextContains(t('Created 7 new menu links'));
    $this->assertSession()->pageTextContains(t('Generate process complete.'));
  }

}
