<?php

namespace Drupal\Tests\entity_usage\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;

/**
 * Tests layout builder usage through Inline Blocks displays in UI.
 *
 * @group entity_usage
 * @group layout_builder
 * @coversDefaultClass \Drupal\entity_usage\Plugin\EntityUsage\Track\LayoutBuilder
 */
class EntityUsageLayoutBuilderTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_usage',
    'entity_test',
    'block_content',
    'block',
    'text',
    'user',
    'layout_builder',
    'layout_discovery',
    'field',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    $this->config('entity_usage.settings')
      ->set('local_task_enabled_entity_types', ['entity_test'])
      ->set('track_enabled_source_entity_types', ['entity_test', 'block_content'])
      ->set('track_enabled_target_entity_types', ['entity_test', 'block_content'])
      ->set('track_enabled_plugins', ['layout_builder', 'entity_reference'])
      ->save();

    /** @var \Drupal\Core\Routing\RouteBuilderInterface $routerBuilder */
    $routerBuilder = \Drupal::service('router.builder');
    $routerBuilder->rebuild();
  }

  /**
   * Test entities referenced by block content in LB are shown on usage page.
   *
   * E.g, if entityHost (with LB) -> Block Content -> entityInner, when
   * navigating to entityInner, the source relationship is shown as ultimately
   * coming from entityHost (via Block Content).
   */
  public function testLayoutBuilderInlineAndReusableBlockUsage(): void {
    $innerEntity = EntityTest::create(['name' => $this->randomMachineName()]);
    $innerEntity->save();
    $innerEntity2 = EntityTest::create(['name' => $this->randomMachineName()]);
    $innerEntity2->save();

    $type = BlockContentType::create([
      'id' => 'foo',
      'label' => 'Foo',
    ]);
    $type->save();

    $fieldStorage = FieldStorageConfig::create([
      'field_name' => 'my_ref',
      'entity_type' => 'block_content',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ]);
    $fieldStorage->save();
    $field = FieldConfig::create([
      'field_storage' => $fieldStorage,
      'bundle' => $type->id(),
    ]);
    $field->save();

    $block = BlockContent::create([
      'type' => $type->id(),
      'reusable' => 0,
      'my_ref' => $innerEntity,
    ]);
    $block->save();

    $block2 = BlockContent::create([
      'type' => $type->id(),
      'reusable' => 1,
      'my_ref' => $innerEntity2,
    ]);
    $block2->save();

    $sectionData = [
      new Section('layout_onecol', [], [
        'first-uuid' => new SectionComponent('first-uuid', 'content', [
          'id' => 'inline_block:' . $type->id(),
          'block_revision_id' => $block->getRevisionId(),
        ]),
        'second-uuid' => new SectionComponent('second-uuid', 'content', [
          'id' => 'block_content:' . $block2->uuid(),
        ]),
      ]),
    ];

    $entityHost = EntityTest::create([
      'name' => $this->randomMachineName(),
      OverridesSectionStorage::FIELD_NAME => $sectionData,
    ]);
    $entityHost->save();

    $this->drupalLogin($this->drupalCreateUser([
      'access entity usage statistics',
      'view test entity',
    ]));

    $this->assertInnerEntityUsage($innerEntity, $entityHost);
    $this->assertInnerEntityUsage($innerEntity2, $entityHost);
  }

  /**
   * Asserts that a host entity is listed against the usage of an inner entity.
   */
  protected function assertInnerEntityUsage(EntityTest $inner, EntityTest $host): void {
    $this->drupalGet(Url::fromRoute('entity.entity_test.entity_usage', ['entity_test' => $inner->id()]));
    $this->assertSession()->statusCodeEquals(200);
    $row = $this->assertSession()->elementExists('css', 'table tbody tr:nth-child(1)');
    $link = $this->assertSession()->elementExists('css', 'td:nth-child(1) a', $row);
    $this->assertEquals($host->label(), $link->getText());
    $this->assertEquals($link->getAttribute('href'), $host->toUrl()->toString());
  }

}
