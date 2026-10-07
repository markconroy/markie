<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\workflows\Entity\Workflow;

/**
 * Integration tests for rich-text image description support.
 *
 * Exercises the real helper entity resolution, metadata storage and the
 * automator description/flagging flow against the echoai test provider.
 *
 * @group ai_automators
 */
class RichTextImageDescriptionKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * A 1x1 GIF used as a real managed image file.
   */
  protected const VALID_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00\x21\xf9\x04\x01\x00\x00\x00\x00\x2c\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x01\x44\x00\x3b";

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_automators',
    'ai_test',
    'workflows',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'node', 'filter']);

    // The helper access-checks embedded files/media against the current user.
    $this->setUpCurrentUser([], ['access content']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->createField('body', 'text_long', 'Body');
    $this->createField('field_summary', 'text_long', 'Summary');
    $this->createField('field_meta', 'string_long', 'Metadata');

    // A content-moderation workflow with a "flagged" state on articles.
    $workflow = Workflow::create([
      'id' => 'editorial',
      'type' => 'content_moderation',
      'label' => 'Editorial',
    ]);
    // The content_moderation workflow type already ships draft + published
    // states; only the "flagged" state and a transition into it are new.
    $plugin = $workflow->getTypePlugin();
    $plugin->addState('flagged', 'Flagged');
    $plugin->addTransition('flag', 'Flag', ['draft', 'published', 'flagged'], 'flagged');
    $plugin->addEntityTypeAndBundle('node', 'article');
    $workflow->save();

    // Point the default chat + vision providers at the echo test provider.
    $this->config('ai.settings')
      ->set('default_providers', [
        'chat' => ['provider_id' => 'echoai', 'model_id' => 'gpt-test'],
        'chat_with_image_vision' => ['provider_id' => 'echoai', 'model_id' => 'gpt-test'],
      ])
      ->save();
  }

  /**
   * Creates a single-cardinality field on the article node type.
   */
  protected function createField(string $name, string $type, string $label): void {
    if (!FieldStorageConfig::loadByName('node', $name)) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'type' => $type,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => $label,
    ])->save();
  }

  /**
   * Creates a real managed image file and returns it.
   */
  protected function createImageFile(string $name): File {
    $uri = 'public://' . $name;
    file_put_contents($uri, self::VALID_GIF);
    $file = File::create([
      'uri' => $uri,
      'filename' => $name,
      'status' => 1,
    ]);
    $file->save();
    return $file;
  }

  /**
   * The rich-text image helper service.
   */
  protected function helper() {
    return \Drupal::service('ai_automator.rule_helper.rich_text_image');
  }

  /**
   * Tests an embedded managed file image is resolved to a file candidate.
   */
  public function testHelperResolvesEmbeddedFileImage(): void {
    $file = $this->createImageFile('embedded.gif');
    $html = '<p>Lead</p><img data-entity-uuid="' . $file->uuid() . '" data-entity-type="file" alt="A cat">';

    $candidates = $this->helper()->extractImageCandidates($html, FALSE, 5);

    $this->assertCount(1, $candidates);
    $this->assertSame('file', $candidates[0]['source_type']);
    $this->assertSame((int) $file->id(), (int) $candidates[0]['file']->id());
    $this->assertSame('A cat', $candidates[0]['alt']);
  }

  /**
   * Tests an unresolvable embedded image is counted but not a candidate.
   */
  public function testHelperCountsUnresolvableImage(): void {
    $html = '<img data-entity-uuid="00000000-0000-0000-0000-000000000000" data-entity-type="file">';

    $summary = $this->helper()->extractImageCandidateSummary($html, FALSE, 5);

    $this->assertSame(1, $summary['encountered_count']);
    $this->assertCount(0, $summary['candidates']);
    $this->assertTrue($summary['has_unprocessed_images']);
  }

  /**
   * Tests metadata is written as JSON to a text field.
   */
  public function testStoreMetadataWritesJson(): void {
    $node = Node::create(['type' => 'article', 'title' => 'Meta']);
    $node->save();

    $this->helper()->storeMetadata($node, 'field_meta', [['description' => 'A skyline.']]);

    $decoded = json_decode($node->get('field_meta')->value, TRUE);
    $this->assertSame('A skyline.', $decoded[0]['description']);
  }

  /**
   * Tests the automator appends image descriptions to the prompt context.
   */
  public function testAutomatorAppendsImageDescriptions(): void {
    $file = $this->createImageFile('inline.gif');
    $node = Node::create([
      'type' => 'article',
      'title' => 'With image',
      'body' => [
        'value' => '<p>Some text.</p><img data-entity-uuid="' . $file->uuid() . '" data-entity-type="file" alt="A cat">',
        'format' => 'plain_text',
      ],
    ]);
    $node->save();

    $instance = \Drupal::service('plugin.manager.ai_automator')->createInstance('llm_text_long');
    $config = [
      'base_field' => 'body',
      'include_image_descriptions' => TRUE,
      'max_image_descriptions' => 5,
      'ai_provider' => 'echoai',
      'ai_model' => 'gpt-test',
    ];

    $tokens = $instance->generateTokens($node, $node->getFieldDefinition('field_summary'), $config, 0);

    $this->assertNotSame('', $tokens['image_descriptions']);
    $this->assertStringContainsString('Image descriptions:', $tokens['context']);
  }

  /**
   * Tests moderation flagging and metadata persistence through storeValues().
   *
   * Covers both the forced "flagged" state when the image limit is exceeded and
   * that image-description metadata is written to the configured field through
   * the real generate()/storeValues() flow on a saved entity.
   */
  public function testModerationFlaggedWhenLimitExceeded(): void {
    $file1 = $this->createImageFile('mod1.gif');
    $file2 = $this->createImageFile('mod2.gif');
    $node = Node::create([
      'type' => 'article',
      'title' => 'Moderate me',
      'body' => [
        'value' => '<img data-entity-uuid="' . $file1->uuid() . '" data-entity-type="file">'
        . '<img data-entity-uuid="' . $file2->uuid() . '" data-entity-type="file">',
        'format' => 'plain_text',
      ],
      'moderation_state' => 'draft',
    ]);
    $node->save();

    $instance = \Drupal::service('plugin.manager.ai_automator')->createInstance('llm_moderation_state');
    $config = [
      'field_name' => 'moderation_state',
      'base_field' => 'body',
      'prompt' => 'Decide the moderation state. {{ context }}',
      'include_image_descriptions' => TRUE,
      'image_description_metadata_field' => 'field_meta',
      // Only 1 of the 2 images may be analyzed -> limit exceeded.
      'max_image_descriptions' => 1,
      'use_simple_model' => FALSE,
      'store_explanation' => FALSE,
      'trigger_lookup' => ['published' => 'published'],
      'ai_provider' => 'echoai',
      'ai_model' => 'gpt-test',
    ];

    $fieldDefinition = $node->getFieldDefinition('moderation_state');
    // generate() initializes metadata and runs token generation (which detects
    // the over-limit images); storeValues() then applies the flag override and
    // persists the collected metadata.
    $values = $instance->generate($node, $fieldDefinition, $config);
    $instance->storeValues($node, $values, $fieldDefinition, $config);

    // The content is flagged for human review.
    $this->assertSame('flagged', $node->get('moderation_state')->value);

    // Image-description metadata was persisted to the configured field, and
    // includes the "not fully processed" summary row for the skipped image.
    $meta = json_decode($node->get('field_meta')->value, TRUE);
    $this->assertNotEmpty($meta);
    $reasons = array_column($meta, 'reason');
    $this->assertContains('images_not_fully_processed', $reasons);
  }

}
