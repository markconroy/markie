<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\ai_translate\Traits\TextTranslatorStubTrait;
use Drupal\Tests\block_content\Traits\BlockContentCreationTrait;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\block_content\Entity\BlockContent;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\NodeInterface;

/**
 * Base class for AI translation of Layout Builder inline blocks.
 *
 * Sets up a translatable node whose content lives in a Layout Builder inline
 * block, and translates it with the AI call stubbed out. Shared by the tests
 * covering the two competing ways of translating Layout Builder overrides —
 * layout_builder_at (asymmetric) and layout_builder_st (symmetric), which
 * cannot be installed on the same site. Everything up to and including the
 * translation run is identical for both; only the shape of the expected result
 * differs, so the assertions on that result stay in the subclasses.
 *
 * Core traits do the parts core has an API for. Only the inline block override
 * is built here, because core builds those through the Layout Builder UI, which
 * needs JavaScript.
 */
abstract class LayoutBuilderTranslationTestBase extends BrowserTestBase {

  use BlockContentCreationTrait;
  use ContentTranslationTestTrait;
  use ContentTypeCreationTrait;
  use EnableLayoutBuilderTrait;
  use NodeCreationTrait;
  use TextTranslatorStubTrait;

  /**
   * Node type used by these tests.
   */
  protected const NODE_TYPE = 'lb_page';

  /**
   * Content block type placed in the layout.
   */
  protected const BLOCK_TYPE = 'basic';

  /**
   * Target language of the translation under test.
   */
  protected const TARGET_LANGCODE = 'nl';

  /**
   * Body text of the inline block before translation.
   */
  protected const BLOCK_BODY = 'The block body in English';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * Subclasses add the Layout Builder translation module under test; Drupal
   * merges $modules up the class hierarchy.
   *
   * @see \Drupal\Core\Test\FunctionalTestSetupTrait::installModulesFromClassProperty()
   */
  protected static $modules = [
    'node',
    'field',
    'text',
    'filter',
    'block',
    'block_content',
    'layout_builder',
    'language',
    'content_translation',
    'ai',
    'ai_translate',
  ];

  /**
   * Whether the layout field instance should be made translatable.
   *
   * Core creates the layout_section field instance non-translatable. Sites
   * running layout_builder_at turn it on in the content language settings —
   * a documented install step of that module — which is what gives each
   * translation its own layout. layout_builder_st needs it left off, because
   * it shares one layout across translations.
   *
   * @return bool
   *   TRUE to make the layout field instance translatable.
   */
  abstract protected function layoutFieldIsTranslatable(): bool;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    static::createLanguageFromLangcode(static::TARGET_LANGCODE);

    $this->createContentType([
      'type' => static::NODE_TYPE,
      'name' => 'Layout Builder page',
      'new_revision' => FALSE,
    ]);
    $this->createBlockContentType([
      'id' => static::BLOCK_TYPE,
      'label' => 'Basic block',
      'revision' => FALSE,
    ], TRUE);

    $this->enableContentTranslation('node', static::NODE_TYPE);
    $this->enableContentTranslation('block_content', static::BLOCK_TYPE);
    static::setFieldTranslatable('block_content', static::BLOCK_TYPE, 'body', TRUE);

    // Enabling Layout Builder overrides creates the layout_section field.
    // layout_builder_at makes the field *storage* translatable from a presave
    // hook, so this has to happen after that module is installed.
    $this->enableLayoutBuilder(
      EntityViewDisplay::load('node.' . static::NODE_TYPE . '.default')
    );

    if ($this->layoutFieldIsTranslatable()) {
      static::setFieldTranslatable('node', static::NODE_TYPE, OverridesSectionStorage::FIELD_NAME, TRUE);
    }

    $this->assertSame(
      $this->layoutFieldIsTranslatable(),
      $this->container->get('entity_field.manager')
        ->getFieldDefinitions('node', static::NODE_TYPE)[OverridesSectionStorage::FIELD_NAME]
        ->isTranslatable(),
      'The layout field translation setting matches the module under test.'
    );

    $this->stubTextTranslator();
  }

  /**
   * Creates a node with a layout override holding one inline block.
   *
   * @param string $title
   *   Title of the node.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node, reloaded so the layout carries the saved block revision.
   */
  protected function createNodeWithInlineBlock(string $title): NodeInterface {
    $block = BlockContent::create([
      'type' => static::BLOCK_TYPE,
      'info' => 'Inline block',
      'reusable' => FALSE,
      'langcode' => 'en',
      'body' => [
        'value' => static::BLOCK_BODY,
        'format' => 'plain_text',
      ],
    ]);

    $component = new SectionComponent(
      $this->container->get('uuid')->generate(),
      'content',
      [
        'id' => 'inline_block:' . static::BLOCK_TYPE,
        'label' => 'Inline block',
        'label_display' => FALSE,
        'provider' => 'layout_builder',
        'view_mode' => 'full',
        // Layout Builder saves this block and swaps in its revision id from
        // InlineBlockEntityOperations::handlePreSave(), the same way the UI
        // does.
        'block_serialized' => serialize($block),
        'context_mapping' => [],
      ],
    );
    $section = new Section('layout_onecol', [], [$component->getUuid() => $component]);

    $node = $this->createNode([
      'type' => static::NODE_TYPE,
      'title' => $title,
      'langcode' => 'en',
      'status' => TRUE,
      OverridesSectionStorage::FIELD_NAME => [$section],
    ]);

    return $this->reloadNode($node);
  }

  /**
   * Reloads a node from storage, bypassing the static cache.
   *
   * A thin wrapper over EntityStorageInterface::loadUnchanged() purely to keep
   * the node type on the return value, so the call sites can reach the
   * translation methods. Drupal\Tests\EntityTrait::reloadEntity() does the
   * same thing generically, but returns EntityInterface.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to reload.
   *
   * @return \Drupal\node\NodeInterface
   *   The freshly loaded node.
   */
  protected function reloadNode(NodeInterface $node): NodeInterface {
    return $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadUnchanged($node->id());
  }

  /**
   * Returns the inline block entities referenced by a layout field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity holding the layout override.
   *
   * @return \Drupal\block_content\BlockContentInterface[]
   *   The inline blocks, keyed by block content id.
   */
  protected function getInlineBlocks(ContentEntityInterface $entity): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('block_content');
    $blocks = [];
    /** @var \Drupal\layout_builder\Field\LayoutSectionItemList $sections */
    $sections = $entity->get(OverridesSectionStorage::FIELD_NAME);
    foreach ($sections->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        $configuration = $component->get('configuration');
        if (empty($configuration['block_revision_id'])) {
          continue;
        }
        $block = $storage->loadRevision($configuration['block_revision_id']);
        if ($block) {
          $blocks[$block->id()] = $block;
        }
      }
    }
    return $blocks;
  }

  /**
   * Asserts that the layout field produced text metadata for translation.
   *
   * Asserted separately from the translation result, because extraction is
   * where Layout Builder content is most easily lost without a trace.
   * TextExtractor::shouldExtract() consults field access, and core's
   * LayoutSectionItemList forbids 'view' on the layout_section field for every
   * account — both layout_builder_at and layout_builder_st leave that in
   * place. An extractor for that field has to be reached in spite of it, and
   * when it is not, nothing downstream complains: the translation saves
   * successfully, just without any layout content.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity to extract from.
   *
   * @see \Drupal\layout_builder\Field\LayoutSectionItemList::defaultAccess()
   */
  protected function assertLayoutTextMetadataExtracted(ContentEntityInterface $entity): void {
    $metadata = $this->container->get('ai_translate.text_extractor')
      ->extractTextMetadata($entity);

    $layout_metadata = array_filter($metadata, static fn (array $item): bool =>
      ($item['field_name'] ?? NULL) === OverridesSectionStorage::FIELD_NAME);

    $this->assertNotEmpty(
      $layout_metadata,
      'Text metadata was extracted from the Layout Builder field.'
    );

    $values = [];
    foreach ($layout_metadata as $item) {
      foreach ($item['_columns'] as $column) {
        if (isset($item[$column])) {
          $values[] = $item[$column];
        }
      }
    }
    $this->assertContains(
      static::BLOCK_BODY,
      $values,
      'The inline block body was collected for translation.'
    );
  }

}
