<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Tests AI translation of Layout Builder blocks with layout_builder_st.
 *
 * With layout_builder_st one layout override is shared across translations, so
 * a translated node must reuse the same inline blocks and gain a translation of
 * each of them.
 *
 * @group ai_translate
 * @covers \Drupal\ai_translate\TextExtractor::shouldExtract
 * @covers \Drupal\ai_translate\Plugin\FieldTextExtractor\LbFieldExtractor
 */
class LayoutBuilderSymmetricTranslationTest extends LayoutBuilderTranslationTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['layout_builder_st'];

  /**
   * {@inheritdoc}
   */
  protected function layoutFieldIsTranslatable(): bool {
    return FALSE;
  }

  /**
   * Tests that the layout field is offered to the translator at all.
   *
   * Symmetric translation keeps core's unconditional AccessResult::forbidden()
   * on the layout_section field, so the extractor has the same obstacle to
   * clear here as in the asymmetric case.
   */
  public function testLayoutTextIsExtractedForTranslation(): void {
    $node = $this->createNodeWithInlineBlock('Symmetric source');

    $this->assertFalse(
      $node->get(OverridesSectionStorage::FIELD_NAME)->access('view'),
      'Core forbids view access to the layout field.'
    );

    $this->assertLayoutTextMetadataExtracted($node);
  }

  /**
   * Tests that translating a node translates its shared inline blocks.
   */
  public function testInlineBlockIsTranslated(): void {
    $node = $this->createNodeWithInlineBlock('Symmetric source');
    $source_blocks = $this->getInlineBlocks($node);
    $this->assertCount(1, $source_blocks);
    $source_block_id = array_key_first($source_blocks);

    $result = $this->container->get('ai_translate.translation_orchestrator')
      ->translateEntity($node, 'en', static::TARGET_LANGCODE);
    $this->assertTrue($result->isSuccess(), 'The translation was saved.');
    $this->assertSame([], $result->getFailures());

    $translation = $this->reloadNode($node)->getTranslation(static::TARGET_LANGCODE);
    $this->assertSame(
      $this->stubTranslationOf('Symmetric source', static::TARGET_LANGCODE),
      $translation->label()
    );

    // Symmetric translation keeps one layout and one set of blocks.
    $this->assertSame(
      [$source_block_id],
      array_keys($this->getInlineBlocks($translation)),
      'The translation reuses the source inline block.'
    );

    $block = $this->container->get('entity_type.manager')
      ->getStorage('block_content')
      ->loadUnchanged($source_block_id);
    $this->assertTrue(
      $block->hasTranslation(static::TARGET_LANGCODE),
      'The inline block gained a translation.'
    );
    $this->assertSame(
      $this->stubTranslationOf(static::BLOCK_BODY, static::TARGET_LANGCODE),
      $block->getTranslation(static::TARGET_LANGCODE)->get('body')->value,
      'The block translation carries the translated body.'
    );
    $this->assertSame(
      static::BLOCK_BODY,
      $block->get('body')->value,
      'The original block body is unchanged.'
    );
  }

}
