<?php

namespace Drupal\Tests\entity_usage\Functional;

use Drupal\filter\Entity\FilterFormat;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\Traits\Core\CronRunTrait;

/**
 * Tests changing a path alias and what happens.
 *
 * @group entity_usage
 */
class EntityUsagePathAliasChangeTest extends BrowserTestBase {
  use CronRunTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_usage',
    'filter',
    'node',
    'path',
    'path_alias',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up the filter formats used by this test.
    $basic_html_format = FilterFormat::create([
      'format' => 'basic_html',
      'name' => 'Basic HTML',
      'filters' => [
        'filter_html' => [
          'status' => 1,
          'settings' => [
            'allowed_html' => '<p> <br> <strong> <a href> <em>',
          ],
        ],
      ],
    ]);
    $basic_html_format->save();

    $current_request = \Drupal::request();
    $this->config('entity_usage.settings')
      ->set('local_task_enabled_entity_types', ['node'])
      ->set('track_enabled_source_entity_types', ['node'])
      ->set('track_enabled_target_entity_types', ['node'])
      ->set('track_enabled_plugins', ['html_link'])
      ->set('site_domains', [$current_request->getHttpHost() . $current_request->getBasePath()])
      ->save();

    /** @var \Drupal\Core\Routing\RouteBuilderInterface $routerBuilder */
    $routerBuilder = \Drupal::service('router.builder');
    $routerBuilder->rebuild();

    $this->drupalLogin($this->drupalCreateUser(admin: TRUE));

    $this->drupalCreateContentType(['type' => 'page']);
  }

  /**
   * Tests tracking with path aliases changing.
   *
   * @testWith [false]
   *           [true]
   */
  public function testTrackingAliasChange(bool $use_cron): void {
    if ($use_cron) {
      \Drupal::service('module_installer')->install(['entity_usage_url_updater_test']);
    }
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create node 1.
    $this->drupalGet('/node/add/page');
    $page->fillField('title[0][value]', 'One');
    $page->fillField('path[0][alias]', '/test-one');
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been created.');

    // So path aliases are respected.
    $this->rebuildContainer();
    $node1 = $this->drupalGetNodeByTitle('One');
    $this->drupalGet('/node/add/page');
    $page->fillField('title[0][value]', 'Two');
    $page->fillField('body[0][value]', (string) $node1->toLink("Link", options: ['absolute' => TRUE])->toString());
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been created.');

    $targets = \Drupal::service('entity_usage.usage')->listTargets($this->drupalGetNodeByTitle('Two'));
    $this->assertCount(1, $targets);
    $this->assertCount(1, $targets['node']);
    $this->assertCount(1, $targets['node'][1]);
    $this->assertSame([
      "method" => "html_link",
      "field_name" => "body",
      "count" => "1",
    ], $targets['node'][1][0]);

    $this->drupalGet('/node/1/usage');
    $assert_session->linkExists('Two');
    $assert_session->linkByHrefExists('node/2');

    $this->drupalGet('/node/1/edit');
    $page->fillField('path[0][alias]', '/test-1');
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been updated.');

    $this->drupalGet('/node/2');
    $this->clickLink('Link');
    $assert_session->statusCodeEquals(404);

    if ($use_cron) {
      $this->drupalGet('/node/1/usage');
      $assert_session->linkExists('Two');
      $assert_session->linkByHrefExists('node/2');

      // This should update the usage to be correct.
      $this->cronRun();
    }
    $this->drupalGet('/node/1/usage');
    $assert_session->linkNotExists('Two');
    $assert_session->linkByHrefNotExists('node/2');
    $assert_session->pageTextContains('There are no recorded usages for entity of type: node with id: 1');

    $targets = \Drupal::service('entity_usage.usage')->listTargets($this->drupalGetNodeByTitle('Two'));
    $this->assertEmpty($targets);
  }

  /**
   * Tests tracking with redirect enabled and path aliases changing.
   */
  public function testTrackingRedirectAndAliasChange(): void {
    \Drupal::service('module_installer')->install(['redirect']);
    $this->config('entity_usage.settings')
      ->set('track_enabled_source_entity_types', ['node', 'redirect'])
      ->set('track_enabled_target_entity_types', ['node', 'redirect'])
      ->set('track_enabled_plugins', ['html_link', 'link'])
      ->save();
    $this->rebuildAll();
    $session = $this->getSession();
    $page = $session->getPage();
    $assert_session = $this->assertSession();

    // Create node 1.
    $this->drupalGet('/node/add/page');
    $page->fillField('title[0][value]', 'One');
    $page->fillField('path[0][alias]', '/test-one');
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been created.');

    // So path aliases are respected.
    $this->rebuildContainer();
    $node1 = $this->drupalGetNodeByTitle('One');
    $this->drupalGet('/node/add/page');
    $page->fillField('title[0][value]', 'Two');
    $page->fillField('body[0][value]', (string) $node1->toLink("Link", options: ['absolute' => TRUE])->toString());
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been created.');

    $targets = \Drupal::service('entity_usage.usage')->listTargets($this->drupalGetNodeByTitle('Two'));
    $this->assertCount(1, $targets);
    $this->assertCount(1, $targets['node']);
    $this->assertCount(1, $targets['node'][1]);
    $this->assertSame([
      "method" => "html_link",
      "field_name" => "body",
      "count" => "1",
    ], $targets['node'][1][0]);

    $this->drupalGet('/node/1/usage');
    $assert_session->linkExists('Two');
    $assert_session->linkByHrefExists('node/2');

    $this->drupalGet('/node/1/edit');
    $page->fillField('path[0][alias]', '/test-1');
    $page->pressButton('Save');
    $assert_session->pageTextContains('has been updated.');

    $this->drupalGet('/node/2');
    $this->clickLink('Link');
    $assert_session->statusCodeEquals(200);

    $this->drupalGet('/node/1/usage');
    $assert_session->linkNotExists('Two');
    $assert_session->linkByHrefNotExists('node/2');
    $assert_session->linkExists('test-one');
    $assert_session->linkByHrefExists('redirect/edit/1');

    $targets = \Drupal::service('entity_usage.usage')->listTargets($this->drupalGetNodeByTitle('Two'));
    $this->assertCount(1, $targets);
    $this->assertCount(1, $targets['redirect']);
    $this->assertCount(1, $targets['redirect'][1]);
    $this->assertSame([
      "method" => "html_link",
      "field_name" => "body",
      "count" => "1",
    ], $targets['redirect'][1][0]);
  }

}
