<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\block_content\Entity\BlockContent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI disclosure of blocks translated by symmetric translation.
 *
 * @group ai_translate
 * @requires module ai_disclosure
 */
#[RunTestsInSeparateProcesses]
#[Group('ai_translate')]
class LayoutBuilderSymmetricDisclosureKernelTest extends LayoutBuilderDisclosureKernelTestBase {

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
    'layout_discovery',
    'system',
    'text',
    'user',
  ];

  /**
   * Tests that the translated block is disclosed and the source is not.
   */
  public function testTranslatedBlockIsDisclosed(): void {
    $block = $this->createBlockContent(FALSE, 'text_block', TRUE, ['field_text' => 'Source block text']);
    $this->translateLayout($this->createLayoutEntity($block));

    $reloaded = BlockContent::load($block->id());
    $this->assertTrue($reloaded->hasTranslation('fr'));
    $translation = $reloaded->getTranslation('fr');
    $this->assertSame('[fr] Source block text', $translation->get('field_text')->value);
    $this->assertSame('ai_translated', $translation->get(static::FIELD_NAME)->grade);
    $this->assertTrue($reloaded->getUntranslated()->get(static::FIELD_NAME)->isEmpty());
  }

  /**
   * Tests that a translated block does not inherit the human review.
   */
  public function testTranslatedBlockDoesNotInheritHumanReview(): void {
    $block = $this->createBlockContent(FALSE, 'text_block', TRUE, [
      'field_text' => 'Source block text',
      static::FIELD_NAME => [
        'mode' => 'custom',
        'grade' => 'ai_assisted_hitl',
        'human_review' => TRUE,
        'description' => 'Reviewed.',
      ],
    ]);
    $this->translateLayout($this->createLayoutEntity($block));

    $reloaded = BlockContent::load($block->id());
    $item = $reloaded->getTranslation('fr')->get(static::FIELD_NAME)->first();
    $this->assertSame('ai_assisted_hitl', $item->get('grade')->getValue());
    $this->assertEmpty($item->get('human_review')->getValue());
    $this->assertEmpty($item->get('description')->getValue());
    $this->assertTrue((bool) $reloaded->getUntranslated()->get(static::FIELD_NAME)->first()->get('human_review')->getValue());
  }

}
