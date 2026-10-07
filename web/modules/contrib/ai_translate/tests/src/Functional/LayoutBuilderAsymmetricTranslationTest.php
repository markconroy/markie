<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Tests AI translation of Layout Builder blocks with layout_builder_at.
 *
 * With layout_builder_at every translation gets its own layout override, so a
 * translated node must end up with its own cloned inline blocks carrying the
 * translated text.
 *
 * @group ai_translate
 * @covers \Drupal\ai_translate\TextExtractor::shouldExtract
 * @covers \Drupal\ai_translate\Plugin\FieldTextExtractor\LbFieldExtractor
 */
class LayoutBuilderAsymmetricTranslationTest extends LayoutBuilderTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_builder_at'];

  /**
   * {@inheritdoc}
   */
  protected function layoutFieldIsTranslatable(): bool {
    return TRUE;
  }

  /**
   * Tests that the layout field is offered to the translator at all.
   *
   * Kept separate from the end-to-end test so that a failure points at
   * extraction rather than at anything the translation does afterwards.
   */
  public function testLayoutTextIsExtractedForTranslation(): void {
    $node = $this->createNodeWithInlineBlock('Asymmetric source');

    // Characterizes the core behavior the extractor has to work around: no
    // account can view the layout field. Should core ever grant that access
    // (#2942975), this is the assertion that will say so.
    $this->assertFalse(
      $node->get(OverridesSectionStorage::FIELD_NAME)->access('view'),
      'Core forbids view access to the layout field.'
    );

    $this->assertLayoutTextMetadataExtracted($node);
  }

  /**
   * Tests that translating a node translates its inline blocks.
   */
  public function testInlineBlockIsTranslated(): void {
    $node = $this->createNodeWithInlineBlock('Asymmetric source');
    $source_block_ids = array_keys($this->getInlineBlocks($node));
    $this->assertCount(1, $source_block_ids);

    $result = $this->container->get('ai_translate.translation_orchestrator')
      ->translateEntity($node, 'en', static::TARGET_LANGCODE);
    $this->assertTrue($result->isSuccess(), 'The translation was saved.');
    $this->assertSame([], $result->getFailures());

    $translation = $this->reloadNode($node)->getTranslation(static::TARGET_LANGCODE);
    $this->assertSame(
      $this->stubTranslationOf('Asymmetric source', static::TARGET_LANGCODE),
      $translation->label()
    );

    $translated_blocks = $this->getInlineBlocks($translation);
    $this->assertCount(1, $translated_blocks, 'The translation has its own inline block.');

    $translated_block = reset($translated_blocks);
    $this->assertSame(
      $this->stubTranslationOf(static::BLOCK_BODY, static::TARGET_LANGCODE),
      $translated_block->get('body')->value,
      'The block in the translated layout carries the translated body.'
    );
    $this->assertNotContains(
      $translated_block->id(),
      $source_block_ids,
      'The translation references a cloned block, not the source block.'
    );
    $this->assertSame(
      static::TARGET_LANGCODE,
      $translated_block->language()->getId(),
      'The cloned block is in the target language.'
    );

    // The source layout must be untouched.
    $source_blocks = $this->getInlineBlocks($this->reloadNode($node));
    $this->assertSame($source_block_ids, array_keys($source_blocks));
    $this->assertSame(
      static::BLOCK_BODY,
      reset($source_blocks)->get('body')->value
    );
  }

}
