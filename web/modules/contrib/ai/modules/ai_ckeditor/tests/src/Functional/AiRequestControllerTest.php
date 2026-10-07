<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\editor\Entity\Editor;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the AiRequest controller endpoint contract.
 *
 * Covers the route permission and that the endpoint keeps working when
 * clients send missing, unknown or inaccessible entity context, since the
 * entity context is optional and must be dropped silently rather than
 * breaking the editor request.
 *
 * @group ai_ckeditor
 */
class AiRequestControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'filter',
    'editor',
    'ckeditor5',
    'ai',
    'ai_test',
    'ai_ckeditor',
    'key',
    'file',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A node to send as entity context.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A text format with the completion plugin configured against the echo
    // test provider.
    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
    ])->save();
    Editor::create([
      'format' => 'test_format',
      'editor' => 'ckeditor5',
      'settings' => [
        'toolbar' => [
          'items' => ['aickeditor'],
        ],
        'plugins' => [
          'ai_ckeditor_ai' => [
            'plugins' => [
              'ai_ckeditor_completion' => [
                'enabled' => TRUE,
                'provider' => 'echoai__test',
              ],
            ],
          ],
        ],
      ],
      'image_upload' => [
        'status' => FALSE,
      ],
    ])->save();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->node = Node::create([
      'type' => 'article',
      'title' => 'Entity context test',
    ]);
    $this->node->save();
  }

  /**
   * Posts a JSON payload to the AI request endpoint as the current user.
   *
   * @param array $payload
   *   The JSON payload.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function postAiRequest(array $payload) {
    $url = $this->buildUrl('/api/ai-ckeditor/request/test_format/ai_ckeditor_completion');
    return $this->getHttpClient()->request('POST', $url, [
      'cookies' => $this->getSessionCookies(),
      'json' => $payload,
      'http_errors' => FALSE,
    ]);
  }

  /**
   * The endpoint requires the use ai ckeditor permission.
   */
  public function testEndpointRequiresPermission(): void {
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    $response = $this->postAiRequest([
      'prompt' => 'Say hello.',
    ]);
    $this->assertSame(403, $response->getStatusCode());
  }

  /**
   * A permitted user gets a provider response, with or without context.
   */
  public function testEndpointRespondsForPermittedUser(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'use ai ckeditor',
    ]));

    // Without entity context.
    $response = $this->postAiRequest([
      'prompt' => 'Say hello.',
    ]);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('Hello world!', (string) $response->getBody());

    // With valid entity context.
    $response = $this->postAiRequest([
      'prompt' => 'Say hello.',
      'entity_type' => 'node',
      'entity_id' => (string) $this->node->id(),
    ]);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertStringContainsString('Hello world!', (string) $response->getBody());
  }

  /**
   * Hostile or invalid entity context never breaks the request.
   */
  public function testInvalidEntityContextIsDroppedSilently(): void {
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'use ai ckeditor',
    ]));

    $payloads = [
      // Unknown entity type.
      ['entity_type' => 'not_a_type', 'entity_id' => '1'],
      // Non-existent entity.
      ['entity_type' => 'node', 'entity_id' => '99999'],
      // Structurally invalid id.
      ['entity_type' => 'node', 'entity_id' => 'DROP TABLE node'],
      // Entity the user cannot view (unpublished, not the owner).
      ['entity_type' => 'node', 'entity_id' => (string) $this->createUnpublishedNode()->id()],
    ];
    foreach ($payloads as $context) {
      $response = $this->postAiRequest($context + ['prompt' => 'Say hello.']);
      $this->assertSame(200, $response->getStatusCode());
      $this->assertStringContainsString('Hello world!', (string) $response->getBody());
    }
  }

  /**
   * Creates an unpublished node owned by the admin user.
   *
   * @return \Drupal\node\NodeInterface
   *   The unpublished node.
   */
  protected function createUnpublishedNode() {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished',
      'status' => 0,
      'uid' => 1,
    ]);
    $node->save();
    return $node;
  }

}
