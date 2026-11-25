<?php

namespace Drupal\Tests\ctools\Kernel\Plugin\Block;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\block\Entity\Block;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\ctools\Plugin\Block\EntityView;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\SchemaCheckTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the entity_view block plugin.
 *
 * @coversDefaultClass \Drupal\ctools\Plugin\Block\EntityView
 *
 * @group ctools
 */
#[Group('ctools')]
#[RunTestsInSeparateProcesses]
class EntityViewTest extends KernelTestBase {

  use NodeCreationTrait;
  use UserCreationTrait;
  use SchemaCheckTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'ctools',
    'filter',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
  }

  /**
   * Tests plugin access.
   *
   * @covers ::access
   */
  public function testAccess() {
    // Create an unpublished node.
    $node = $this->createNode(['status' => 0]);

    $configuration = [
      'view_mode' => 'default',
    ];
    $definition = [
      'context_definitions' => [
        'entity' => new EntityContextDefinition('entity:node', NULL, TRUE, FALSE, NULL, $node),
      ],
      'provider' => 'ctools',
    ];
    $block = EntityView::create($this->container, $configuration, 'entity_view:node', $definition);
    $block->setContextValue('entity', $node);

    $access = $block->access(\Drupal::currentUser());
    $this->assertFalse($access);

    // Add a user than can see the unpublished block.
    $account = $this->createUser([], NULL, TRUE);
    $access = $block->access($account);
    $this->assertTrue($access);
  }

  /**
   * Tests the entity_view block config schema.
   */
  public function testBlockConfigSchema(): void {
    $id = strtolower($this->randomMachineName());
    $block = Block::create([
      'id' => $id,
      'theme' => 'stark',
      'weight' => 0,
      'status' => TRUE,
      'region' => 'content',
      'plugin' => 'entity_view:node',
      'settings' => [
        'label' => $this->randomMachineName(),
        'provider' => 'ctools',
        'label_display' => FALSE,
        'context_mapping' => ['entity' => '@node.node_route_context:node'],
        'view_mode' => 'default',
      ],
      'visibility' => [],
    ]);
    $block->save();
    $this->assertConfigSchemaByName("block.block.$id");
  }

  /**
   * Tests that rendering the same entity recursively is detected and blocked.
   *
   * @covers ::trackRecursion
   * @covers ::releaseRecursion
   */
  public function testRecursionDetection(): void {
    // Intercept the E_USER_WARNING triggered on recursive rendering attempts.
    set_error_handler(function (int $errno, string $errstr): bool {
      return $errno === E_USER_WARNING && str_starts_with($errstr, 'Recursive rendering attempt aborted');
    });

    $node = $this->createNode();
    $definition = [
      'context_definitions' => [
        'entity' => new EntityContextDefinition('entity:node', NULL, TRUE, FALSE, NULL, $node),
      ],
      'provider' => 'ctools',
    ];
    $block = EntityView::create($this->container, ['view_mode' => 'default'], 'entity_view:node', $definition);
    $block->setContextValue('entity', $node);
    $build = $block->build();

    // First render: no recursion in progress, should be allowed.
    $result = $block->trackRecursion($build);
    $this->assertArrayNotHasKey('#printed', $result);

    // Simulated nested call: recursion is detected and the render is skipped.
    $result = $block->trackRecursion($build);
    $this->assertTrue($result['#printed']);

    // After the outer render completes, the entity can be rendered again.
    $block->releaseRecursion('', $build);
    $result = $block->trackRecursion($build);
    $this->assertArrayNotHasKey('#printed', $result);
    $block->releaseRecursion('', $build);

    restore_error_handler();
  }

  /**
   * Tests that access is denied when the entity context has no value.
   *
   * @covers ::access
   */
  public function testAccessIsDeniedWithoutEntity(): void {
    $node = $this->createNode();
    $account = $this->createUser([], NULL, TRUE);
    $definition = [
      'context_definitions' => [
        'entity' => new EntityContextDefinition('entity:node', NULL, TRUE, FALSE, NULL, $node),
      ],
      'provider' => 'ctools',
    ];
    $block = EntityView::create($this->container, ['view_mode' => 'default'], 'entity_view:node', $definition);

    // A required context only returns NULL, rather than throwing, once its
    // typed data has been initialized, as ::hasContextValue() does.
    $context = EntityContext::fromEntityTypeId('node');
    $this->assertFalse($context->hasContextValue());
    $block->setContext('entity', $context);

    $this->assertFalse($block->access($account));
    $this->assertTrue($block->access($account, TRUE)->isForbidden());
  }

  /**
   * Tests that the same entity can be rendered more than once per request.
   *
   * @covers ::build
   */
  public function testRepeatedRender(): void {
    // The node's created date formatter needs the system date formats.
    $this->installConfig(['system']);
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $node = $this->createNode([
      'type' => 'page',
      'title' => 'Repeated render node',
    ]);
    $definition = [
      'context_definitions' => [
        'entity' => new EntityContextDefinition('entity:node', NULL, TRUE, FALSE, NULL, $node),
      ],
      'provider' => 'ctools',
    ];
    $renderer = $this->container->get('renderer');

    foreach ([1, 2] as $attempt) {
      // Bypass the render cache so every render runs the recursion guard.
      $this->container->get('cache.render')->deleteAll();
      $block = EntityView::create($this->container, ['view_mode' => 'default'], 'entity_view:node', $definition);
      $block->setContextValue('entity', $node);
      $build = $block->build();
      $this->assertNotEmpty($build, "Build $attempt is not empty.");
      $this->assertStringContainsString('Repeated render node', (string) $renderer->renderInIsolation($build), "Render $attempt contains the node.");
    }
  }

}
