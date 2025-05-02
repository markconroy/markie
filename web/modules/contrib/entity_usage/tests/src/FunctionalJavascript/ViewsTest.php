<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\node\Entity\Node;

/**
 * Basic tests for the views integration.
 *
 * @package Drupal\Tests\entity_usage\FunctionalJavascript
 *
 * @group entity_usage
 */
class ViewsTest extends EntityUsageJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views',
  ];

  /**
   * Tests the views integration.
   */
  public function testViewsIntegration(): void {
    $page = $this->getSession()->getPage();

    // Create node 1.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 1');
    $page->pressButton('Save');
    $this->assertSession()->pageTextContains('Entity Usage test content Node 1 has been created.');
    $this->saveHtmlOutput();

    // Create node 2 referencing node 1 using reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 2');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $this->assertSession()->pageTextContains('Entity Usage test content Node 2 has been created.');
    $node2 = Node::load(2);
    $this->saveHtmlOutput();

    // Create node 3 also referencing node 1 in a reference field.
    $this->drupalGet('/node/add/eu_test_ct');
    $page->fillField('title[0][value]', 'Node 3');
    $page->fillField('field_eu_test_related_nodes[0][target_id]', 'Node 1 (1)');
    $page->pressButton('Save');
    $this->assertSession()->pageTextContains('Entity Usage test content Node 3 has been created.');
    $this->saveHtmlOutput();

    // Visit the view and check that the usage is correctly tracked there.
    $this->drupalGet('/eu-basic-test-view');
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->responseContains('<td headers="view-count-table-column" class="views-field views-field-count">2          </td>');

    // Delete node 2 and verify that the view updates.
    $node2->delete();
    $this->drupalGet('/eu-basic-test-view');
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->responseContains('<td headers="view-count-table-column" class="views-field views-field-count">1          </td>');
  }

}
