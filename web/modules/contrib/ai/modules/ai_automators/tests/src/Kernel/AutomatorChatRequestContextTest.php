<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\ai\Event\PreGenerateResponseEvent;
use Drupal\ai_automators\Entity\AiAutomator;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests the tags and entity_context automator chat requests carry.
 *
 * Several automators may be configured on one field (for example separate
 * Field Widget Action buttons for "Default", "Short summary" and "Bullet
 * outline"). Their requests share the entity type, bundle and field tags, so
 * each request must also carry its own ai_automator:id tag, and structured
 * entity_context metadata that works even before the entity is saved.
 *
 * @group ai_automators
 *
 * @see \Drupal\ai_automators\PluginBaseClasses\RuleBase::getTags()
 * @see \Drupal\ai_automators\PluginBaseClasses\RuleBase::buildEntityContext()
 */
class AutomatorChatRequestContextTest extends KernelTestBase {

  /**
   * The automator config entity IDs configured on the shared field.
   */
  protected const AUTOMATOR_IDS = [
    'node.article.field_summary.default',
    'node.article.field_summary.short_summary',
    'node.article.field_summary.bullet_outline',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'node',
    'text',
    'token',
    'filter',
    'key',
    'ai',
    'ai_test',
    'ai_automators',
    'field_widget_actions',
  ];

  /**
   * The provider requests captured during the test, in dispatch order.
   *
   * Each entry holds the request 'tags' and the 'entity_context' metadata.
   *
   * @var array[]
   */
  protected array $requests = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('ai_mock_provider_result');
    $this->installConfig(['system', 'field', 'node', 'filter', 'ai', 'ai_test']);

    NodeType::create([
      'type' => 'article',
      'name' => 'Article',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_summary',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_summary',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Summary',
    ])->save();

    // Three automators on the same field, differing only by ID and prompt.
    // The runtime automator config is assembled from the automator_ prefixed
    // plugin_config keys (see AiAutomatorEntityModifier::entityHasConfig()).
    $weight = 0;
    foreach (self::AUTOMATOR_IDS as $id) {
      $label = ucfirst(str_replace('_', ' ', substr($id, strrpos($id, '.') + 1)));
      $prompt = $label . ': {{ context }}';
      AiAutomator::create([
        'id' => $id,
        'label' => $label,
        'rule' => 'llm_simple_string',
        'input_mode' => 'base',
        'weight' => $weight += 10,
        'worker_type' => 'direct',
        'entity_type' => 'node',
        'bundle' => 'article',
        'field_name' => 'field_summary',
        'edit_mode' => TRUE,
        'base_field' => 'title',
        'prompt' => $prompt,
        'token' => '',
        'plugin_config' => [
          'automator_enabled' => 1,
          'automator_rule' => 'llm_simple_string',
          'automator_mode' => 'base',
          'automator_base_field' => 'title',
          'automator_prompt' => $prompt,
          'automator_token' => '',
          'automator_edit_mode' => 1,
          'automator_label' => $label,
          'automator_weight' => (string) $weight,
          'automator_worker_type' => 'direct',
          'automator_ai_provider' => 'echoai',
          'automator_ai_model' => 'default',
        ],
      ])->save();
    }

    $this->container->get('event_dispatcher')->addListener(
      PreGenerateResponseEvent::EVENT_NAME,
      function (PreGenerateResponseEvent $event): void {
        $this->requests[] = [
          'tags' => $event->getTags(),
          'entity_context' => $event->getMetadata('entity_context'),
        ];
      },
    );
  }

  /**
   * Running each automator on a saved node emits its own ID tag.
   *
   * This is the path a Field Widget Action button takes: a single automator
   * is selected by ID for one field of an existing entity.
   */
  public function testFieldWidgetPathEmitsDistinctIdTagsForSameField(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Article',
    ]);
    // Presave runs the automators once; only the explicit runs below matter.
    $node->save();
    $this->requests = [];

    /** @var \Drupal\ai_automators\AiAutomatorEntityModifier $modifier */
    $modifier = $this->container->get('ai_automator.entity_modifier');
    foreach (self::AUTOMATOR_IDS as $id) {
      $node->set('field_summary', NULL);
      $modifier->saveEntity($node, FALSE, 'field_summary', FALSE, $id);
    }

    $this->assertCount(count(self::AUTOMATOR_IDS), $this->requests, 'Each automator run produced exactly one provider request.');

    $shared_tags = [
      'chat',
      'ai_automator',
      'ai_automator:type:llm_simple_string',
      'ai_automator:entity_type:node',
      'ai_automator:entity:' . $node->id(),
      'ai_automator:bundle:article',
      'ai_automator:field_name:field_summary',
    ];
    $seen_ids = [];
    foreach ($this->requests as $index => $request) {
      $expected_id = self::AUTOMATOR_IDS[$index];
      $id_tags = $this->idTags($request['tags']);
      $this->assertSame(['ai_automator:id:' . $expected_id], $id_tags, 'The request carries exactly one ID tag, for the automator that ran.');
      $seen_ids[] = $id_tags[0];
      $this->assertSame($shared_tags, $this->withoutIdTags($request['tags']), 'All other tags are identical across the automators on the field.');

      $context = $request['entity_context'];
      $this->assertIsArray($context, 'entity_context is attached to the request.');
      $this->assertSame('node', $context['entity_type']);
      $this->assertEquals($node->id(), $context['entity_id']);
      $this->assertTrue(is_numeric($context['entity_id']), 'A saved node exposes its numeric ID.');
      $this->assertSame($node->uuid(), $context['uuid']);
      $this->assertSame('article', $context['bundle']);
      $this->assertSame('field_summary', $context['field_name']);
      $this->assertSame($expected_id, $context['automator_id']);
    }
    $this->assertCount(count(self::AUTOMATOR_IDS), array_unique($seen_ids), 'Every automator emitted a distinct ID tag.');
  }

  /**
   * Presave on an unsaved node still identifies the automator and entity.
   *
   * The entity has no ID yet, so the legacy ai_automator:entity: tag is
   * empty. entity_context keeps entity_id NULL and exposes the uuid instead.
   */
  public function testPresaveEmitsEntityContextForUnsavedEntity(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Brand new article',
    ]);
    $uuid = $node->uuid();
    $node->save();

    $this->assertCount(count(self::AUTOMATOR_IDS), $this->requests, 'Every automator on the field ran during presave.');

    $seen_ids = [];
    foreach ($this->requests as $request) {
      $id_tags = $this->idTags($request['tags']);
      $this->assertCount(1, $id_tags, 'Presave requests carry exactly one ID tag.');
      $seen_ids[] = $id_tags[0];
      $this->assertContains('ai_automator:entity:', $request['tags'], 'The legacy entity tag has no ID for an unsaved node.');
      $this->assertContains('ai_automator:entity_type:node', $request['tags']);

      $context = $request['entity_context'];
      $this->assertIsArray($context, 'entity_context is attached during presave.');
      $this->assertSame('node', $context['entity_type']);
      $this->assertArrayHasKey('entity_id', $context);
      $this->assertNull($context['entity_id'], 'An unsaved node has no entity_id yet.');
      $this->assertSame($uuid, $context['uuid'], 'The uuid identifies the unsaved node.');
      $this->assertSame('article', $context['bundle']);
      $this->assertSame('field_summary', $context['field_name']);
      $this->assertSame(substr($id_tags[0], strlen('ai_automator:id:')), $context['automator_id'], 'automator_id matches the ID tag of the same request.');
    }
    sort($seen_ids);
    $expected = array_map(fn (string $id): string => 'ai_automator:id:' . $id, self::AUTOMATOR_IDS);
    sort($expected);
    $this->assertSame($expected, $seen_ids, 'Each automator emitted its own ID tag during presave.');
  }

  /**
   * Returns only the ai_automator:id tags from a tag list.
   *
   * @param string[] $tags
   *   The tags.
   *
   * @return string[]
   *   The ID tags, reindexed.
   */
  protected function idTags(array $tags): array {
    return array_values(array_filter($tags, fn (string $tag): bool => str_starts_with($tag, 'ai_automator:id:')));
  }

  /**
   * Returns a tag list without its ai_automator:id tags.
   *
   * @param string[] $tags
   *   The tags.
   *
   * @return string[]
   *   The remaining tags, reindexed.
   */
  protected function withoutIdTags(array $tags): array {
    return array_values(array_filter($tags, fn (string $tag): bool => !str_starts_with($tag, 'ai_automator:id:')));
  }

}
