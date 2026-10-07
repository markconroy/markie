<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Verifies html_entity_decode() in label-matching rules.
 *
 * When a model returns a JSON value like "Latin America &amp; Caribbean" the
 * automator must match it against the raw stored label "Latin America &
 * Caribbean". Commit c63d80a4 adds html_entity_decode() before the in_array /
 * == comparisons in Lists::verifyValue(), Lists::storeValues(),
 * Taxonomy::verifyValue(), and Taxonomy::storeValues(). These tests assert
 * that the decode is in place and that the negative-control path still rejects
 * unknown values.
 *
 * @group ai_automators
 */
class LabelEntityDecodeTest extends KernelTestBase {

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
    'options',
    'ai',
    'ai_automators',
    'taxonomy',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['system', 'field', 'node', 'filter']);

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
  }

  /**
   * Lists::verifyValue() accepts an HTML-entity-encoded label.
   */
  public function testListStringVerifyValueDecodesEntities(): void {
    $this->createListField(['latam' => 'Latin America & Caribbean']);
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();
    $rule = $this->listStringRule();
    $fieldDef = $node->getFieldDefinition('field_list');

    $this->assertTrue(
      $rule->verifyValue($node, 'Latin America &amp; Caribbean', $fieldDef, []),
      'Entity-encoded label should be accepted after decode.',
    );
    $this->assertFalse(
      $rule->verifyValue($node, 'Totally Unknown', $fieldDef, []),
      'Unknown label should still be rejected.',
    );
  }

  /**
   * Lists::verifyValue() also handles < > " ' entities (ENT_QUOTES|ENT_HTML5).
   */
  public function testListStringVerifyValueDecodesAllHtmlEntities(): void {
    $this->createListField(['special' => "Tom & Jerry's <show>"]);
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();
    $rule = $this->listStringRule();
    $fieldDef = $node->getFieldDefinition('field_list');

    $this->assertTrue(
      $rule->verifyValue($node, "Tom &amp; Jerry&apos;s &lt;show&gt;", $fieldDef, []),
      'All HTML special-char entities should decode correctly.',
    );
  }

  /**
   * Lists::storeValues() maps an entity-encoded label to its storage key.
   */
  public function testListStringStoreValuesMapsEncodedLabelToKey(): void {
    $this->createListField(['latam' => 'Latin America & Caribbean']);
    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();
    $rule = $this->listStringRule();
    $fieldDef = $node->getFieldDefinition('field_list');

    $rule->storeValues($node, ['Latin America &amp; Caribbean'], $fieldDef, []);

    $this->assertSame(
      'latam',
      $node->get('field_list')->value,
      'storeValues() should resolve the entity-encoded label to its storage key.',
    );
  }

  /**
   * Taxonomy::verifyValue() accepts an HTML-entity-encoded term name.
   */
  public function testTaxonomyVerifyValueDecodesEntities(): void {
    [$node] = $this->createTaxonomyScenario('Latin America & Caribbean');
    $rule = $this->taxonomyRule();
    $fieldDef = $node->getFieldDefinition('field_tags');

    $this->assertTrue(
      $rule->verifyValue($node, 'Latin America &amp; Caribbean', $fieldDef, []),
      'Entity-encoded term name should be accepted after decode.',
    );
    $this->assertFalse(
      $rule->verifyValue($node, 'Totally Unknown', $fieldDef, []),
      'Unknown term name should still be rejected.',
    );
  }

  /**
   * Taxonomy::storeValues() resolves an entity-encoded name to the term id.
   */
  public function testTaxonomyStoreValuesSetsTermId(): void {
    [$node, $term] = $this->createTaxonomyScenario('Latin America & Caribbean');
    $rule = $this->taxonomyRule();
    $fieldDef = $node->getFieldDefinition('field_tags');

    $rule->storeValues($node, ['Latin America &amp; Caribbean'], $fieldDef, []);

    $stored = $node->get('field_tags')->getValue();
    $this->assertNotEmpty($stored, 'storeValues() should populate the field.');
    $this->assertSame(
      (string) $term->id(),
      (string) $stored[0]['target_id'],
      'storeValues() should resolve the entity-encoded term name to its tid.',
    );
  }

  /**
   * Returns the llm_list_string plugin instance.
   */
  protected function listStringRule(): object {
    return $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_list_string');
  }

  /**
   * Returns the llm_taxonomy plugin instance.
   */
  protected function taxonomyRule(): object {
    return $this->container
      ->get('plugin.manager.ai_automator')
      ->createInstance('llm_taxonomy');
  }

  /**
   * Creates a list_string field on node/article with the given allowed values.
   */
  protected function createListField(array $allowedValues): void {
    FieldStorageConfig::create([
      'field_name' => 'field_list',
      'entity_type' => 'node',
      'type' => 'list_string',
      'settings' => ['allowed_values' => $allowedValues],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_list',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'List',
    ])->save();
  }

  /**
   * Creates a vocabulary, term, and entity_reference field; returns both.
   *
   * @return array
   *   0 => Node, 1 => Term.
   */
  protected function createTaxonomyScenario(string $termName): array {
    Vocabulary::create(['vid' => 'tags', 'name' => 'Tags'])->save();
    $term = Term::create(['vid' => 'tags', 'name' => $termName]);
    $term->save();

    FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Tags',
      'settings' => [
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => ['tags' => 'tags'],
          'auto_create' => FALSE,
        ],
      ],
    ])->save();

    $node = Node::create(['type' => 'article', 'title' => 'Test']);
    $node->save();

    return [$node, $term];
  }

}
