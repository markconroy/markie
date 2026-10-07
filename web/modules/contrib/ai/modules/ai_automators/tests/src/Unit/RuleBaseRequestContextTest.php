<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the request tags and metadata RuleBase attaches to chat requests.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\PluginBaseClasses\RuleBase
 */
class RuleBaseRequestContextTest extends UnitTestCase {

  /**
   * The uuid reported by the mocked entity.
   */
  protected const UUID = '9d0c6b6e-1f4c-4d3a-9a6d-1c2b3d4e5f60';

  /**
   * Automator config shared by the automators on the test field.
   *
   * @var array
   */
  protected array $baseConfig = [
    'rule' => 'llm_simple_string',
    'field_name' => 'field_summary',
    'base_field' => 'title',
  ];

  /**
   * Two automators on one field emit tag lists that differ only by their ID.
   *
   * @covers ::getTags
   */
  public function testAutomatorsOnSameFieldEmitDistinctIdTags(): void {
    $rule = $this->makeRule();
    $entity = $this->mockEntity('42');

    $default = $rule->getTags('prompt', $this->baseConfig + ['id' => 'node.article.field_summary.default'], new \stdClass(), $entity);
    $outline = $rule->getTags('prompt', $this->baseConfig + ['id' => 'node.article.field_summary.bullet_outline'], new \stdClass(), $entity);

    $this->assertContains('ai_automator:id:node.article.field_summary.default', $default);
    $this->assertContains('ai_automator:id:node.article.field_summary.bullet_outline', $outline);
    $this->assertNotContains('ai_automator:id:node.article.field_summary.bullet_outline', $default);
    $this->assertNotContains('ai_automator:id:node.article.field_summary.default', $outline);

    // Exactly one ID tag per request.
    $this->assertCount(1, $this->idTags($default));
    $this->assertCount(1, $this->idTags($outline));

    // Everything except the ID tag is identical, in the pre-existing order.
    $expected = [
      'ai_automator',
      'ai_automator:type:llm_simple_string',
      'ai_automator:entity_type:node',
      'ai_automator:entity:42',
      'ai_automator:bundle:article',
      'ai_automator:field_name:field_summary',
    ];
    $this->assertSame($expected, $this->withoutIdTags($default));
    $this->assertSame($expected, $this->withoutIdTags($outline));
  }

  /**
   * Configs without an ID keep emitting the legacy tag list unchanged.
   *
   * @covers ::getTags
   */
  public function testNoIdTagWhenConfigLacksId(): void {
    $rule = $this->makeRule();
    $tags = $rule->getTags('prompt', $this->baseConfig, new \stdClass(), $this->mockEntity('42'));

    $this->assertSame([], $this->idTags($tags));
    $this->assertSame([
      'ai_automator',
      'ai_automator:type:llm_simple_string',
      'ai_automator:entity_type:node',
      'ai_automator:entity:42',
      'ai_automator:bundle:article',
      'ai_automator:field_name:field_summary',
    ], $tags);
  }

  /**
   * Non-string IDs are ignored rather than rendered into a broken tag.
   *
   * @covers ::getTags
   */
  public function testNonStringIdIsIgnored(): void {
    $rule = $this->makeRule();
    $tags = $rule->getTags('prompt', $this->baseConfig + ['id' => ['not' => 'a string']], new \stdClass(), NULL);

    $this->assertSame([], $this->idTags($tags));
    $this->assertSame([
      'ai_automator',
      'ai_automator:type:llm_simple_string',
      'ai_automator:field_name:field_summary',
    ], $tags);
  }

  /**
   * The ID tag is emitted even when no entity is available.
   *
   * @covers ::getTags
   */
  public function testIdTagWithoutEntity(): void {
    $rule = $this->makeRule();
    $tags = $rule->getTags('prompt', $this->baseConfig + ['id' => 'node.article.field_summary.default'], new \stdClass(), NULL);

    $this->assertSame([
      'ai_automator',
      'ai_automator:type:llm_simple_string',
      'ai_automator:id:node.article.field_summary.default',
      'ai_automator:field_name:field_summary',
    ], $tags);
  }

  /**
   * A saved entity yields a fully populated entity_context.
   *
   * @covers ::buildEntityContext
   */
  public function testBuildEntityContextForSavedEntity(): void {
    $rule = $this->makeRule();
    $config = $this->baseConfig + ['id' => 'node.article.field_summary.default'];

    $this->assertSame([
      'entity_type' => 'node',
      'entity_id' => '42',
      'uuid' => self::UUID,
      'bundle' => 'article',
      'field_name' => 'field_summary',
      'automator_id' => 'node.article.field_summary.default',
    ], $this->invokeBuild($rule, $this->mockEntity('42'), $config));
  }

  /**
   * An unsaved (presave) entity keeps entity_id NULL but exposes its uuid.
   *
   * @covers ::buildEntityContext
   */
  public function testBuildEntityContextForNewEntityHasNullId(): void {
    $rule = $this->makeRule();
    $context = $this->invokeBuild($rule, $this->mockEntity(NULL, TRUE), $this->baseConfig);

    $this->assertSame('node', $context['entity_type']);
    $this->assertArrayHasKey('entity_id', $context);
    $this->assertNull($context['entity_id']);
    $this->assertSame(self::UUID, $context['uuid']);
    $this->assertSame('article', $context['bundle']);
    $this->assertSame('field_summary', $context['field_name']);
    $this->assertNull($context['automator_id'], 'Configs without an ID leave automator_id empty.');
  }

  /**
   * Entities that are new but already carry an ID are still reported as new.
   *
   * @covers ::buildEntityContext
   */
  public function testBuildEntityContextForNewEntityWithPresetIdIsNull(): void {
    $rule = $this->makeRule();
    $context = $this->invokeBuild($rule, $this->mockEntity('7', TRUE), $this->baseConfig);

    $this->assertNull($context['entity_id']);
  }

  /**
   * Without an entity there is no entity_context.
   *
   * @covers ::buildEntityContext
   */
  public function testBuildEntityContextWithoutEntityReturnsNull(): void {
    $this->assertNull($this->invokeBuild($this->makeRule(), NULL, $this->baseConfig));
  }

  /**
   * The context is attached as request metadata on the input.
   *
   * @covers ::attachEntityContext
   */
  public function testAttachEntityContextSetsRequestMetadata(): void {
    $rule = $this->makeRule();
    $config = $this->baseConfig + ['id' => 'node.article.field_summary.default'];
    $input = new ChatInput([new ChatMessage('user', 'hi')]);

    $this->invokeAttach($rule, $input, $this->mockEntity('42'), $config);

    $context = $input->getRequestMetadataValue('entity_context');
    $this->assertIsArray($context);
    $this->assertSame('node', $context['entity_type']);
    $this->assertSame('42', $context['entity_id']);
    $this->assertSame('node.article.field_summary.default', $context['automator_id']);
    $this->assertSame(['entity_context'], array_keys($input->getAllRequestMetadata()));
  }

  /**
   * Without an entity the request metadata is left untouched.
   *
   * @covers ::attachEntityContext
   */
  public function testAttachEntityContextWithoutEntityIsNoop(): void {
    $rule = $this->makeRule();
    $input = new ChatInput([new ChatMessage('user', 'hi')]);

    $this->invokeAttach($rule, $input, NULL, $this->baseConfig);

    $this->assertSame([], $input->getAllRequestMetadata());
  }

  /**
   * Invokes the protected buildEntityContext() helper.
   */
  protected function invokeBuild(RuleBase $rule, ?ContentEntityInterface $entity, array $config): ?array {
    $method = new \ReflectionMethod($rule, 'buildEntityContext');
    return $method->invoke($rule, $entity, $config);
  }

  /**
   * Invokes the protected attachEntityContext() helper.
   */
  protected function invokeAttach(RuleBase $rule, ChatInput $input, ?ContentEntityInterface $entity, array $config): void {
    $method = new \ReflectionMethod($rule, 'attachEntityContext');
    $method->invoke($rule, $input, $entity, $config);
  }

  /**
   * Builds a content entity mock with the identity used across the tests.
   *
   * @param string|null $id
   *   The entity ID, or NULL for an unsaved entity.
   * @param bool $is_new
   *   Whether the entity reports itself as new.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The mocked entity.
   */
  protected function mockEntity(?string $id, bool $is_new = FALSE): ContentEntityInterface {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('id')->willReturn($id);
    $entity->method('isNew')->willReturn($is_new);
    $entity->method('uuid')->willReturn(self::UUID);
    return $entity;
  }

  /**
   * Returns only the ai_automator:id tags from a tag list.
   *
   * @param string[] $tags
   *   The tags.
   *
   * @return string[]
   *   The ID tags.
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

  /**
   * Builds a RuleBase instance without invoking its constructor.
   *
   * The methods under test only read their arguments, so none of the injected
   * collaborators are needed.
   */
  protected function makeRule(): RuleBase {
    $reflection = new \ReflectionClass(RuleBaseRequestContextTestStub::class);
    /** @var \Drupal\ai_automators\PluginBaseClasses\RuleBase $rule */
    $rule = $reflection->newInstanceWithoutConstructor();
    return $rule;
  }

}

/**
 * Minimal concrete RuleBase for reflection-based instantiation in tests.
 */
class RuleBaseRequestContextTestStub extends RuleBase {

  /**
   * {@inheritdoc}
   */
  public function generate(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeValues(
    ContentEntityInterface $entity,
    array $values,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function verifyValue(
    ContentEntityInterface $entity,
    $value,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return TRUE;
  }

}
