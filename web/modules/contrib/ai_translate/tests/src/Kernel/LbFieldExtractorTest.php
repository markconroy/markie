<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_translate\FieldTextExtractorInterface;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\Block\InlineBlock;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionListInterface;
use Drupal\Tests\ai_translate\Traits\BlockContentCreationTrait;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Layout Builder field text extractor.
 *
 * @group ai_translate
 */
#[RunTestsInSeparateProcesses]
class LbFieldExtractorTest extends KernelTestBase {

  use BlockContentCreationTrait;
  use EnableLayoutBuilderTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_translate',
    'block_content',
    'content_translation',
    'entity_test',
    'field',
    'language',
    'layout_builder',
    'layout_builder_at',
    'layout_discovery',
    'system',
    'text',
    'user',
  ];

  /**
   * The text extractor service.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * The Layout Builder field extractor.
   *
   * @var \Drupal\ai_translate\FieldTextExtractorInterface
   */
  protected FieldTextExtractorInterface $layoutExtractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('entity_test_mulrevpub');

    $this->installConfig(['language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $display = LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test_mulrevpub',
      'bundle' => 'entity_test_mulrevpub',
      'mode' => 'default',
      'status' => TRUE,
    ]);
    $this->enableLayoutBuilder($display);

    $this->createBlockContentType([
      'id' => 'plain_block',
      'label' => 'Plain block',
      'revision' => TRUE,
    ]);

    $this->createBlockContentType([
      'id' => 'text_block',
      'label' => 'Text block',
      'revision' => TRUE,
    ]);

    $this->createStringField('field_text', 'text_block', TRUE);

    $this->textExtractor = $this->container->get('ai_translate.text_extractor');
    $this->layoutExtractor = $this->container->get('plugin.manager.text_extractor')
      ->getExtractor('layout_section');
  }

  /**
   * Tests that layouts with only non-text blocks emit metadata.
   */
  public function testExtractReturnsSentinelForInlineBlocksWithoutText(): void {
    $block = $this->createBlockContent(FALSE, 'plain_block');
    $block->set('info', '');
    $block->save();

    $entity = $this->createLayoutEntity([$block]);

    $metadata = $this->layoutExtractor->extract(
      $entity,
      OverridesSectionStorage::FIELD_NAME,
    );

    $this->assertCount(1, $metadata);
    $this->assertArrayHasKey('_layout_builder_inline_blocks_present', $metadata[0]);
  }

  /**
   * Tests that text-bearing blocks still return regular metadata.
   */
  public function testExtractReturnsRegularMetadataWhenTextExists(): void {
    $textBlock = $this->createBlockContent(FALSE, 'text_block', TRUE, ['field_text' => 'Source block text']);
    $entity = $this->createLayoutEntity([$textBlock]);

    $metadata = $this->layoutExtractor->extract(
      $entity,
      OverridesSectionStorage::FIELD_NAME,
    );

    $this->assertNotEmpty($metadata);
    $this->assertArrayHasKey('delta', $metadata[0]);
    $this->assertArrayNotHasKey('_layout_builder_inline_blocks_present', $metadata[0]);
  }

  /**
   * Tests that sentinel metadata triggers block cloning.
   */
  public function testSetValueClonesInlineBlocksForSentinelOnlyMetadata(): void {
    $entity = $this->createLayoutEntity([
      $this->createBlockContent(FALSE, 'plain_block'),
    ]);

    $sourcePlugin = $this->getFirstComponentPlugin($entity);
    $this->layoutExtractor->setValue(
      $entity,
      OverridesSectionStorage::FIELD_NAME,
      [['_layout_builder_inline_blocks_present' => TRUE]],
    );

    $updatedPlugin = $this->getFirstComponentPlugin($this->reloadEntity($entity));

    assert($sourcePlugin instanceof InlineBlock);
    assert($updatedPlugin instanceof InlineBlock);
    $this->assertNotSame($sourcePlugin->getConfiguration()['block_id'], $updatedPlugin->getConfiguration()['block_id']);
  }

  /**
   * Creates a configurable string field on a block bundle.
   */
  protected function createStringField(string $fieldName, string $bundle, bool $translatable): void {
    if (!FieldStorageConfig::loadByName('block_content', $fieldName)) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'block_content',
        'type' => 'string',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      ])->save();
    }

    FieldConfig::create([
      'field_storage' => FieldStorageConfig::loadByName('block_content', $fieldName),
      'bundle' => $bundle,
      'label' => 'Text',
      'translatable' => $translatable,
    ])->save();
  }

  /**
   * Creates a Layout Builder entity containing the given blocks.
   *
   * @param \Drupal\block_content\Entity\BlockContent[] $blocks
   *   The blocks to add.
   */
  protected function createLayoutEntity(array $blocks): EntityTestMulRevPub {
    $section = new Section('layout_onecol');
    foreach ($blocks as $delta => $block) {
      $section->appendComponent($this->createSectionComponent($block, 'component-' . $delta));
    }

    $entity = EntityTestMulRevPub::create([
      'name' => 'Test entity',
      'langcode' => 'en',
    ]);
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME);
    assert($sections instanceof SectionListInterface);
    $sections->appendSection($section);
    $entity->save();

    return $entity;
  }

  /**
   * Creates a section component for a saved block.
   */
  protected function createSectionComponent(BlockContent $block, string $uuid): SectionComponent {
    return new SectionComponent($uuid, 'content', [
      'id' => 'inline_block:' . $block->bundle(),
      'block_id' => $block->id(),
      'block_revision_id' => $block->getRevisionId(),
    ]);
  }

  /**
   * Returns the first source component plugin.
   */
  protected function getFirstComponentPlugin(EntityTestMulRevPub $entity): InlineBlock {
    $components = $entity->get(OverridesSectionStorage::FIELD_NAME)->getValue()[0]['section']->getComponents();
    $component = reset($components);
    $plugin = $component->getPlugin();
    assert($plugin instanceof InlineBlock);
    return $plugin;
  }

  /**
   * Reloads the test entity.
   */
  protected function reloadEntity(EntityTestMulRevPub $entity): EntityTestMulRevPub {
    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test_mulrevpub');
    $storage->resetCache([$entity->id()]);
    $reloaded = $storage->load($entity->id());
    assert($reloaded instanceof EntityTestMulRevPub);
    return $reloaded;
  }

}
