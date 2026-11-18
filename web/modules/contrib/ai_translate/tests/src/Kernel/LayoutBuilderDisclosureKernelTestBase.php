<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionListInterface;
use Drupal\Tests\ai_translate\Traits\BlockContentCreationTrait;
use Drupal\Tests\ai_translate\Traits\TextTranslatorStubTrait;
use Drupal\Tests\layout_builder\Traits\EnableLayoutBuilderTrait;

/**
 * Base class for the AI disclosure tests of translated Layout Builder blocks.
 */
abstract class LayoutBuilderDisclosureKernelTestBase extends KernelTestBase {

  use BlockContentCreationTrait;
  use EnableLayoutBuilderTrait;
  use TextTranslatorStubTrait;

  /**
   * Name of the AI Disclosure field on the block bundle.
   */
  protected const FIELD_NAME = 'field_ai_disclosure';

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('layout_builder', ['inline_block_usage']);
    $this->installSchema('ai_disclosure', ['ai_disclosure_suggestion']);
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installConfig(['language', 'ai_disclosure', 'ai_translate']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->enableLayoutBuilder(LayoutBuilderEntityViewDisplay::create([
      'targetEntityType' => 'entity_test_mulrevpub',
      'bundle' => 'entity_test_mulrevpub',
      'mode' => 'default',
      'status' => TRUE,
    ]));

    $this->createBlockContentType([
      'id' => 'text_block',
      'label' => 'Text block',
      'revision' => TRUE,
    ]);
    FieldStorageConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'block_content',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_text',
      'entity_type' => 'block_content',
      'bundle' => 'text_block',
      'label' => 'Text',
      'translatable' => TRUE,
    ])->save();
    FieldStorageConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'block_content',
      'type' => 'ai_disclosure',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => static::FIELD_NAME,
      'entity_type' => 'block_content',
      'bundle' => 'text_block',
      'label' => 'AI disclosure',
      'translatable' => TRUE,
    ])->save();

    $this->stubTextTranslator();
  }

  /**
   * Translates the layout entity and returns its French translation.
   */
  protected function translateLayout(EntityTestMulRevPub $entity): EntityTestMulRevPub {
    $result = $this->container->get('ai_translate.translation_orchestrator')
      ->translateEntity($entity, 'en', 'fr');
    $this->assertTrue($result->isSuccess());

    $storage = $this->container->get('entity_type.manager')->getStorage('entity_test_mulrevpub');
    $storage->resetCache([$entity->id()]);
    $reloaded = $storage->load($entity->id());
    assert($reloaded instanceof EntityTestMulRevPub);
    return $reloaded->getTranslation('fr');
  }

  /**
   * Creates a Layout Builder entity holding one inline block.
   */
  protected function createLayoutEntity(BlockContent $block): EntityTestMulRevPub {
    $section = new Section('layout_onecol');
    $section->appendComponent(new SectionComponent('component-0', 'content', [
      'id' => 'inline_block:' . $block->bundle(),
      'block_id' => $block->id(),
      'block_revision_id' => $block->getRevisionId(),
    ]));
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

}
