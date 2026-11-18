<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\field\Entity\FieldConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI disclosure of blocks cloned by asymmetric translation.
 *
 * @group ai_translate
 * @requires module ai_disclosure
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class LayoutBuilderDisclosureKernelTest extends LayoutBuilderDisclosureKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_disclosure',
    'ai_translate',
    'block_content',
    'content_translation',
    'entity_test',
    'field',
    'key',
    'language',
    'layout_builder',
    'layout_builder_at',
    'layout_discovery',
    'system',
    'text',
    'user',
  ];

  /**
   * Tests that the cloned block of a translated layout is disclosed.
   */
  public function testClonedBlockIsDisclosed(): void {
    $block = $this->createBlockContent(FALSE, 'text_block', TRUE, ['field_text' => 'Source block text']);
    $translation = $this->translateLayout($this->createLayoutEntity($block));

    $components = $translation->get(OverridesSectionStorage::FIELD_NAME)->getValue()[0]['section']->getComponents();
    $clone = BlockContent::load(reset($components)->get('configuration')['block_id']);

    $this->assertNotEquals($block->id(), $clone->id());
    $this->assertSame('[fr] Source block text', $clone->get('field_text')->value);
    $this->assertSame('ai_translated', $clone->get(static::FIELD_NAME)->grade);
    $this->assertTrue(BlockContent::load($block->id())->get(static::FIELD_NAME)->isEmpty());
  }

  /**
   * Tests that a cloned block does not inherit the human review.
   */
  public function testClonedBlockDoesNotInheritHumanReview(): void {
    $block = $this->createBlockContent(FALSE, 'text_block', TRUE, [
      'field_text' => 'Source block text',
      static::FIELD_NAME => [
        'mode' => 'custom',
        'grade' => 'ai_assisted_hitl',
        'human_review' => TRUE,
        'description' => 'Reviewed.',
      ],
    ]);
    $translation = $this->translateLayout($this->createLayoutEntity($block));

    $components = $translation->get(OverridesSectionStorage::FIELD_NAME)->getValue()[0]['section']->getComponents();
    $clone = BlockContent::load(reset($components)->get('configuration')['block_id']);

    $item = $clone->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_assisted_hitl', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());
    $this->assertEmpty($item->get('description')->getValue());
    $this->assertTrue((bool) BlockContent::load($block->id())->get(static::FIELD_NAME)->first()->get('human_review')->getValue());
  }

  /**
   * Tests that a clone is disclosed even when the field is not translatable.
   */
  public function testCloneNeedsNoTranslatableField(): void {
    FieldConfig::loadByName('block_content', 'text_block', static::FIELD_NAME)
      ->set('translatable', FALSE)
      ->save();
    $block = $this->createBlockContent(FALSE, 'text_block', TRUE, ['field_text' => 'Source block text']);
    $translation = $this->translateLayout($this->createLayoutEntity($block));

    $components = $translation->get(OverridesSectionStorage::FIELD_NAME)->getValue()[0]['section']->getComponents();
    $clone = BlockContent::load(reset($components)->get('configuration')['block_id']);

    $this->assertSame('ai_translated', $clone->get(static::FIELD_NAME)->grade);
    $this->assertTrue(BlockContent::load($block->id())->get(static::FIELD_NAME)->isEmpty());
  }

}
