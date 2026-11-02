<?php

declare(strict_types=1);

namespace Drupal\Tests\token\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests token replacement during node preview.
 *
 * @group token
 */
#[Group('token')]
#[RunTestsInSeparateProcesses]
class TokenNodePreviewTest extends TokenTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    $account = $this->drupalCreateUser([
      'create page content',
      'access content',
    ]);
    $this->drupalLogin($account);
  }

  /**
   * Ensures URL tokens do not throw during preview of an unsaved node.
   */
  public function testPreviewUrlTokens(): void {
    $this->drupalGet('node/add/page');
    $this->submitForm([
      'title[0][value]' => 'Preview title',
    ], 'Preview');

    $path = parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH) ?? '';
    preg_match('#/preview/([^/]+)/#', $path, $matches);
    $uuid = $matches[1] ?? NULL;

    $store = \Drupal::service('tempstore.private')->get('node_preview');
    $preview_state = $store->get($uuid);
    $this->assertNotEmpty($preview_state, 'Preview form state found in temp store.');

    $node = $preview_state->getFormObject()->getEntity();
    $this->assertTrue($node->isNew());
    $this->assertNotEmpty($node->in_preview);

    // Use nested URL tokens; base [node:url] is handled in core.
    $input = '[node:url:relative] [node:url:absolute] [node:url:path]';
    $output = \Drupal::token()->replace($input, ['node' => $node], ['clear' => FALSE]);
    $this->assertSame($input, $output);
  }

}
