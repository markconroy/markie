<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\ai_translate\TextExtractorInterface;
use Drupal\comment\CommentInterface;
use Drupal\comment\Entity\Comment;
use Drupal\comment\Tests\CommentTestTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests text extraction support for configurable and base fields.
 *
 * @group ai_translate
 */
class TextExtractorBaseFieldSupportTest extends KernelTestBase {

  use CommentTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'comment',
    'taxonomy',
    'language',
    'content_translation',
    'entity_test',
    'ai',
    'ai_translate',
    'ai_translate_test',
  ];

  /**
   * The text extractor service.
   */
  protected TextExtractorInterface $textExtractor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_mul');
    $this->installEntitySchema('comment');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('comment', ['comment_entity_statistics']);
    $this->installConfig(['comment', 'field', 'taxonomy']);

    Vocabulary::create([
      'vid' => 'tags',
      'name' => 'Tags',
    ])->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    FieldStorageConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_test_body',
      'type' => 'string',
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'bundle' => 'entity_test_mul',
      'field_name' => 'field_test_body',
      'label' => 'Test body',
      'translatable' => TRUE,
    ])->save();

    FieldStorageConfig::create([
      'entity_type' => 'entity_test_mul',
      'field_name' => 'field_ai_translate_test',
      'type' => 'ai_translate_test',
      'translatable' => TRUE,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test_mul',
      'bundle' => 'entity_test_mul',
      'field_name' => 'field_ai_translate_test',
      'label' => 'Test field',
      'translatable' => TRUE,
    ])->save();

    $this->addDefaultCommentField('entity_test', 'entity_test', 'comment');

    $this->textExtractor = $this->container->get('ai_translate.text_extractor');
  }

  /**
   * Tests extraction from a regular configurable textual field.
   */
  public function testConfigurableTextFieldIsExtracted(): void {
    $entity = EntityTestMul::create([
      'name' => 'Config text entity',
      'field_test_body' => 'Configurable body text',
    ]);

    $field_names = array_column($this->textExtractor->extractTextMetadata($entity), 'field_name');

    $this->assertContains('field_test_body', $field_names);
  }

  /**
   * Tests extraction and insertion for translatable comment field_name.
   */
  public function testCommentFieldNameBaseFieldRoundTripWorks(): void {
    $host = EntityTest::create([
      'name' => 'Comment host',
    ]);
    $host->save();

    $comment = Comment::create([
      'comment_type' => 'comment',
      'entity_type' => 'entity_test',
      'entity_id' => $host->id(),
      'field_name' => 'comment',
      'pid' => 0,
      'uid' => 0,
      'status' => CommentInterface::PUBLISHED,
      'subject' => 'Comment subject',
      'hostname' => '127.0.0.1',
      'langcode' => 'en',
      'comment_body' => [['value' => 'Comment body']],
    ]);
    $comment->save();

    $metadata = $this->textExtractor->extractTextMetadata($comment);
    $field_names = array_column($metadata, 'field_name');

    $this->assertContains('field_name', $field_names);

    $field_name_metadata = array_values(array_filter(
      $metadata,
      static fn (array $item): bool => $item['field_name'] === 'field_name'
    ));

    $this->assertCount(1, $field_name_metadata);
    $this->assertSame('comment', $field_name_metadata[0]['value']);

    $field_name_metadata[0]['translated'] = [
      'value' => 'comment_translated',
    ];
    $field_name_metadata[0]['field_properties'] = [];

    $translation = $comment->addTranslation('fr');
    $this->textExtractor->insertTextMetadata($translation, $field_name_metadata);

    $this->assertSame('comment_translated', $translation->get('field_name')->value);
  }

  /**
   * Tests extraction from a configurable test field plugin.
   */
  public function testConfigurableTestPluginFieldIsExtracted(): void {
    $entity = EntityTestMul::create([
      'name' => 'Config test entity',
      'field_ai_translate_test' => 'Test value',
    ]);

    $metadata = $this->textExtractor->extractTextMetadata($entity);
    $field_names = array_column($metadata, 'field_name');

    $this->assertContains('field_ai_translate_test', $field_names);

    $field_test_metadata = array_values(array_filter(
      $metadata,
      static fn (array $item): bool => $item['field_name'] === 'field_ai_translate_test'
    ));

    $this->assertCount(1, $field_test_metadata);
    $this->assertSame('Test value', $field_test_metadata[0]['value']);

    $field_test_metadata[0]['translated'] = [
      'value' => 'Translated test value',
    ];
    $field_test_metadata[0]['field_properties'] = [];

    $translation = $entity->addTranslation('fr');
    $this->textExtractor->insertTextMetadata($translation, $field_test_metadata);

    $this->assertSame('Translated test value', $translation->get('field_ai_translate_test')->value);
  }

  /**
   * Tests that a base field for an unchanged test plugin is skipped.
   */
  public function testBaseTestPluginFieldIsSkipped(): void {
    $entity = EntityTestMul::create([
      'name' => 'Base test entity',
      'base_ai_translate_test' => 'Base test value',
    ]);

    $field_names = array_column($this->textExtractor->extractTextMetadata($entity), 'field_name');

    $this->assertNotContains('base_ai_translate_test', $field_names);
  }

  /**
   * Tests extraction from the taxonomy term description base field.
   */
  public function testTaxonomyDescriptionBaseFieldIsExtracted(): void {
    $term = Term::create([
      'vid' => 'tags',
      'name' => 'Tag one',
      'description' => [
        'value' => 'Taxonomy description text',
      ],
    ]);

    $field_names = array_column($this->textExtractor->extractTextMetadata($term), 'field_name');

    $this->assertContains('description', $field_names);
  }

}
