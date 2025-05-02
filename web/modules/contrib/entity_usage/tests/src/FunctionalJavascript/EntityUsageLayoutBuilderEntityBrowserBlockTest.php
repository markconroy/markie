<?php

namespace Drupal\Tests\entity_usage\FunctionalJavascript;

use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;
use Drupal\Tests\entity_usage\Traits\EntityUsageLastEntityQueryTrait;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * Tests usage tracking in Layout Builder through Entity Browser Blocks.
 *
 * @group entity_usage
 * @group layout_builder
 * @coversDefaultClass \Drupal\entity_usage\Plugin\EntityUsage\Track\LayoutBuilder
 */
class EntityUsageLayoutBuilderEntityBrowserBlockTest extends EntityUsageJavascriptTestBase {

  use ContextualLinkClickTrait;
  use EntityUsageLastEntityQueryTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'text',
    'user',
    'layout_builder',
    'layout_discovery',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'article',
    ]);
    $node_type->save();

    LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $node_type->id(),
      'mode' => 'default',
      'status' => TRUE,
    ])->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->config('entity_usage.settings')
      ->set('local_task_enabled_entity_types', ['node'])
      ->set('track_enabled_source_entity_types', ['node'])
      ->set('track_enabled_target_entity_types', ['node'])
      ->set('track_enabled_plugins', ['layout_builder', 'entity_reference'])
      ->save();

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('page_title_block');

    /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
    $router_builder = \Drupal::service('router.builder');
    $router_builder->rebuild();
  }

  /**
   * Test usage tracking in Layout Builder through Entity Browser Block.
   */
  public function testLayoutBuilderEntityBrowserBlockUsage(): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    \Drupal::service('module_installer')->install([
      'entity_browser_block',
      'entity_browser_entity_form',
    ], TRUE);

    $widget_uuid = \Drupal::service('uuid')->generate();

    // Create an entity browser we can use when testing.
    $browser = \Drupal::entityTypeManager()
      ->getStorage('entity_browser')
      ->create([
        'name' => 'eu_test_browser',
        'label' => 'Entity Usage - Test Entity Browser',
        'display' => 'modal',
        'display_configuration' => [
          'width' => '',
          'height' => '',
          'link_text' => 'Select new TEB',
          'auto_open' => FALSE,
        ],
        'selection_display' => 'no_display',
        'selection_display_configuration' => [],
        'widget_selector' => 'tabs',
        'widget_selector_configuration' => [],
        'widgets' => [
          $widget_uuid => [
            'id' => 'entity_form',
            'label' => 'Create new',
            'weight' => 0,
            'uuid' => $widget_uuid,
            'settings' => [
              'entity_type' => 'node',
              'bundle' => 'article',
              'form_mode' => 'default',
              'submit_text' => 'Save node',
            ],
          ],
        ],

      ]);
    $browser->save();

    /** @var \Drupal\Core\Routing\RouteBuilderInterface $router_builder */
    $router_builder = \Drupal::service('router.builder');
    $router_builder->rebuild();

    // This is the source node.
    $host_node = Node::create([
      'title' => 'Host node 1',
      'type' => 'article',
    ]);
    $host_node->save();

    // Adjust permissions as needed.
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load('authenticated');
    $this->grantPermissions($role, [
      'configure all article node layout overrides',
      'access eu_test_browser entity browser pages',
      'access entity usage statistics',
      'access contextual links',
    ]);

    // Add a target node in the host node's layout through EBB.
    $this->drupalGet("/node/{$host_node->id()}/layout");
    $page->clickLink('Add block in Section 1, Content region');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    // Open the EBB config form.
    $ebb_link = $assert_session->elementExists('css', '#drupal-off-canvas a:contains("Entity Usage - Test Entity Browser")');
    $ebb_link->click();
    // Launch the EB in the modal window.
    $ebb_button = $assert_session->waitForElementVisible('css', '#drupal-off-canvas input[value="Select new TEB"]');
    $ebb_button->press();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'iframe[name="entity_browser_iframe_eu_test_browser"]'));
    // Unfortunately the iframe isn't immediately available to be switched
    // into, for some reason.
    $session->wait(5000);
    $this->getSession()->switchToIFrame('entity_browser_iframe_eu_test_browser');
    // Give the target node a title and save it.
    $page->fillField('Title', 'First target node');
    $page->pressButton('Save node');
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $this->getSession()->switchToIFrame();
    // Wait for the table to finish loading.
    $assert_session->waitForElement('css', '#drupal-off-canvas table .entity-browser-block-delta-order');
    // Verify we have selected in the block config the node that was created.
    $assert_session->elementTextContains('css', '#drupal-off-canvas table', 'First target node');
    // Insert the block in LB.
    $add_block_button = $assert_session->elementExists('css', '#drupal-off-canvas input[value="Add block"]');
    $add_block_button->press();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas', 5000);
    $this->saveHtmlOutput();
    // Verify it shows up in the LB preview.
    $assert_session->pageTextContains('You have unsaved changes');
    $block_selector = '.layout-builder__section .layout-builder__region .layout-builder-block article';
    $rendered_node = $assert_session->elementExists('css', $block_selector);
    $this->assertStringContainsString('First target node', $rendered_node->getText());
    // Save the Layout and verify the node appears in the FE as well.
    $page->pressButton('Save layout');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The layout override has been saved');
    $block_selector = 'article .layout__region--content';
    $block = $assert_session->elementExists('css', $block_selector);
    $rendered_node = $assert_session->elementExists('css', 'article', $block);
    $this->assertStringContainsStringIgnoringCase('First target node', $rendered_node->getText());

    $first_target_node = $this->getLastEntityOfType('node', TRUE);

    // Visit the node, click the "Usage" tab in there, and check usage is
    // correctly tracked.
    $page->clickLink('First target node');
    $this->saveHtmlOutput();
    $page->clickLink('Usage');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Entity usage information for First target node');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->getTitle(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Layout', $first_row_field_label->getText());
    $assert_session->pageTextNotContains('Old revision(s)');
    $assert_session->pageTextNotContains('Pending revision(s) / Draft(s)');
    $assert_session->pageTextNotContains('Default:');

    // Verify we can edit the layout and add another item to the same region.
    $page->clickLink($host_node->getTitle());
    $page->clickLink('Layout');
    $this->saveHtmlOutput();
    $page->clickLink('Add block in Section 1, Content region');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $ebb_link = $assert_session->elementExists('css', '#drupal-off-canvas a:contains("Entity Usage - Test Entity Browser")');
    $ebb_link->click();
    $ebb_button = $assert_session->waitForElementVisible('css', '#drupal-off-canvas input[value="Select new TEB"]');
    $ebb_button->press();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'iframe[name="entity_browser_iframe_eu_test_browser"]'));
    $session->wait(5000);
    $this->getSession()->switchToIFrame('entity_browser_iframe_eu_test_browser');
    $page->fillField('Title', 'Second target node');
    $page->pressButton('Save node');
    $assert_session->assertWaitOnAjaxRequest();
    $this->saveHtmlOutput();
    $this->getSession()->switchToIFrame();
    // Wait for the table to finish loading.
    $assert_session->waitForElement('css', '#drupal-off-canvas table .entity-browser-block-delta-order');
    $assert_session->elementTextContains('css', '#drupal-off-canvas table', 'Second target node');
    $add_block_button = $assert_session->elementExists('css', '#drupal-off-canvas input[value="Add block"]');
    $add_block_button->press();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas', 5000);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('You have unsaved changes');
    $blocks = $page->findAll('css', '.layout-builder__layout .layout-builder__region .layout-builder-block');
    $rendered_node = $assert_session->elementExists('css', 'article', $blocks[2]);
    $this->assertStringContainsString('Second target node', $rendered_node->getText());
    $page->pressButton('Save layout');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The layout override has been saved');
    $blocks = $page->findAll('css', 'article.contextual-region .layout__region.layout__region--content > div > article');
    $rendered_node = $blocks[1];
    $this->assertStringContainsString('Second target node', $rendered_node->getText());

    // Visit the node, click the "Usage" tab in there, and check usage is OK.
    $page->clickLink('Second target node');
    $page->clickLink('Usage');
    $assert_session->pageTextContains('Entity usage information for Second target node');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->getTitle(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Layout', $first_row_field_label->getText());

    // The usage for the first node is still there.
    $page->clickLink($host_node->getTitle());
    $page->clickLink('First target node');
    $page->clickLink('Usage');
    $assert_session->pageTextContains('Entity usage information for First target node');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->getTitle(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Layout', $first_row_field_label->getText());

    // We can remove the usage as well.
    \Drupal::service('module_installer')->install(['contextual'], TRUE);

    $this->drupalGet("/node/{$host_node->id()}/layout");
    $blocks = $page->findAll('css', '.layout-builder__section .layout-builder__region .layout-builder-block');
    $first_block = $blocks[1];
    $contextual_wrapper = $assert_session->elementExists('css', '.contextual', $first_block);
    $contextual_id = $contextual_wrapper->getAttribute('data-contextual-id');
    $this->clickContextualLink('div[data-contextual-id="' . $contextual_id . '"]', 'Remove block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas'));
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('Are you sure you want to remove');
    $remove_button = $assert_session->elementExists('css', '#drupal-off-canvas input[value="Remove"]');
    $remove_button->press();
    $assert_session->assertNoElementAfterWait('css', '#drupal-off-canvas', 5000);
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('You have unsaved changes');
    $page->pressButton('Save layout');
    $this->saveHtmlOutput();
    $assert_session->pageTextContains('The layout override has been saved');

    // The record is there, but points to previous revisions only.
    $this->drupalGet("/node/{$first_target_node->id()}/usage");
    $assert_session->pageTextContains('Entity usage information for First target node');
    $first_row_title_link = $assert_session->elementExists('xpath', '//table/tbody/tr[1]/td[1]/a');
    $this->assertEquals($host_node->getTitle(), $first_row_title_link->getText());
    $this->assertStringContainsString($host_node->toUrl()->toString(), $first_row_title_link->getAttribute('href'));
    $first_row_field_label = $this->xpath('//table/tbody/tr[1]/td[4]')[0];
    $this->assertEquals('Layout', $first_row_field_label->getText());
    $first_row_used_in = $this->xpath('//table/tbody/tr[1]/td[6]')[0];
    $this->assertEquals('Old revision(s)', $first_row_used_in->getText());
  }

}
