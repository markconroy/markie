<?php

namespace Drupal\Tests\twig_tweak\Kernel;

use Drupal\block\BlockViewBuilder;
use Drupal\block\Entity\Block;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests for the Twig Tweak access control.
 *
 * @group twig_tweak
 */
class AccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * A node for testing.
   *
   * @var \Drupal\node\NodeInterface
   */
  private $node;

  /**
   * The Twig extension.
   *
   * @var \Drupal\twig_tweak\TwigExtension
   */
  private $twigExtension;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'twig_tweak',
    'twig_tweak_test',
    'node',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $node_type->save();

    $values = [
      'type' => 'article',
      'status' => NodeInterface::PUBLISHED,
      // @see twig_tweak_test_node_access()
      'title' => 'Entity access test',
    ];
    $this->node = Node::create($values);
    $this->node->save();

    $this->twigExtension = $this->container->get('twig_tweak.twig_extension');
  }

  /**
   * Test callback.
   */
  public function testDrupalEntity() {

    // -- Unprivileged user.
    $this->setUpCurrentUser(['name' => 'User 1']);

    $build = $this->twigExtension->drupalEntity('node', $this->node->id());
    self::assertNull($build);

    // -- Privileged user.
    $this->setUpCurrentUser(['name' => 'User 2'], ['access content']);

    $build = $this->twigExtension->drupalEntity('node', $this->node->id());
    self::assertArrayHasKey('#node', $build);
    $expected_cache = [
      'tags' => [
        'node:1',
        'node_view',
        'tag_from_twig_tweak_test_node_access',
      ],
      'contexts' => [
        'user',
        'user.permissions',
      ],
      'max-age' => 50,
    ];
    self::assertSame($expected_cache, $build['#cache']);
  }

  /**
   * Test callback.
   */
  public function testDrupalField() {

    // -- Unprivileged user.
    $this->setUpCurrentUser(['name' => 'User 1']);

    $build = $this->twigExtension->drupalField('title', 'node', $this->node->id());
    self::assertNull($build);

    // -- Privileged user.
    $this->setUpCurrentUser(['name' => 'User 2'], ['access content']);

    $build = $this->twigExtension->drupalField('title', 'node', $this->node->id());
    self::assertArrayHasKey('#items', $build);
    $expected_cache = [
      'contexts' => [
        'user',
        'user.permissions',
      ],
      'tags' => [
        'node:1',
        'tag_from_twig_tweak_test_node_access',
      ],
      'max-age' => 50,
    ];
    self::assertSame($expected_cache, $build['#cache']);
  }

  /**
   * Test callback.
   */
  public function testDrupalRegion() {

    // @codingStandardsIgnoreStart
    $create_block = function ($id) {
      return new class(['id' => $id], 'block') extends Block {
        public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
          $result = AccessResult::allowedIf($this->id == 'block_1');
          $result->cachePerUser();
          $result->addCacheTags(['tag_for_' . $this->id]);
          $result->setCacheMaxAge(123);
          return $return_as_object ? $result : $result->isAllowed();
        }
        public function getPlugin() {
          return NULL;
        }
      };
    };
    // @codingStandardsIgnoreEnd

    $storage = $this->createMock(EntityStorageInterface::class);
    $blocks = [
      'block_1' => $create_block('block_1'),
      'block_2' => $create_block('block_2'),
    ];
    $storage->expects($this->any())
      ->method('loadByProperties')
      ->willReturn($blocks);

    $view_builder = $this->createMock(BlockViewBuilder::class);
    $content = [
      '#markup' => 'foo',
      '#cache' => [
        'tags' => ['tag_from_view'],
      ],
    ];
    $view_builder->expects($this->any())
      ->method('view')
      ->willReturn($content);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->willReturn($storage);
    $entity_type_manager->expects($this->any())
      ->method('getViewBuilder')
      ->willReturn($view_builder);

    $this->container->set('entity_type.manager', $entity_type_manager);

    $build = $this->twigExtension->drupalRegion('bar');
    $expected_build = [
      'block_1' => [
        '#markup' => 'foo',
        '#cache' => [
          'tags' => ['tag_from_view'],
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => [
          'tag_for_block_1',
          'tag_for_block_2',
        ],
        'max-age' => 123,
      ],
    ];
    self::assertSame($expected_build, $build);
  }

}
