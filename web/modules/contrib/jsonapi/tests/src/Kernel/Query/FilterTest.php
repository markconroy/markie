<?php

namespace Drupal\Tests\jsonapi\Kernel\Query;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Http\Exception\CacheableBadRequestHttpException;
use Drupal\jsonapi\Normalizer\EntityConditionGroupNormalizer;
use Drupal\jsonapi\Normalizer\EntityConditionNormalizer;
use Drupal\jsonapi\Normalizer\FilterNormalizer;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;

/**
 * @coversDefaultClass \Drupal\jsonapi\Query\Filter
 * @group jsonapi
 * @group jsonapi_query
 * @group legacy
 *
 * @internal
 */
class FilterTest extends JsonapiKernelTestBase {

  use ImageFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'field',
    'file',
    'image',
    'jsonapi',
    'node',
    'serialization',
    'system',
    'text',
    'user',
  ];

  /**
   * The filter denormalizer.
   *
   * @var \Symfony\Component\Serializer\Normalizer\DenormalizerInterface
   */
  protected $normalizer;

  /**
   * A node storage instance.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->setUpSchemas();

    $this->savePaintingType();

    // ((RED or CIRCLE) or (YELLOW and SQUARE))
    $this->savePaintings([
      ['colors' => ['red'], 'shapes' => ['triangle'], 'title' => 'FIND'],
      ['colors' => ['orange'], 'shapes' => ['circle'], 'title' => 'FIND'],
      ['colors' => ['orange'], 'shapes' => ['triangle'], 'title' => 'DONT_FIND'],
      ['colors' => ['yellow'], 'shapes' => ['square'], 'title' => 'FIND'],
      ['colors' => ['yellow'], 'shapes' => ['triangle'], 'title' => 'DONT_FIND'],
      ['colors' => ['orange'], 'shapes' => ['square'], 'title' => 'DONT_FIND'],
    ]);

    $this->normalizer = new FilterNormalizer(
      $this->container->get('jsonapi.field_resolver'),
      new EntityConditionNormalizer(),
      new EntityConditionGroupNormalizer()
    );
    $this->nodeStorage = $this->container->get('entity_type.manager')->getStorage('node');
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueToMissingPropertyName() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The field `colors`, given in the path `colors` is incomplete, it must end with one of the following specifiers: `value`, `format`, `processed`.');
    $normalized = [
      'colors' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueToMissingPropertyNameReferenceFieldWithMetaProperties() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The field `photo`, given in the path `photo` is incomplete, it must end with one of the following specifiers: `id`, `meta.alt`, `meta.title`, `meta.width`, `meta.height`.');
    $normalized = [
      'photo' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueMissingMetaPrefixReferenceFieldWithMetaProperties() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The property `alt`, given in the path `photo.alt` belongs to the meta object of a relationship and must be preceded by `meta`.');
    $normalized = [
      'photo.alt' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueToMissingPropertyNameReferenceFieldWithoutMetaProperties() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The field `uid`, given in the path `uid` is incomplete, it must end with one of the following specifiers: `id`.');
    $normalized = [
      'uid' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueToNonexistentProperty() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The property `foobar`, given in the path `colors.foobar`, does not exist. Must be one of the following property names: `value`, `format`, `processed`.');
    $normalized = [
      'colors.foobar' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testInvalidFilterPathDueToElidedSoleProperty() {
    $this->setExpectedException(CacheableBadRequestHttpException::class, 'Invalid nested filtering. The property `value`, given in the path `promote.value`, does not exist. Filter by `promote`, not `promote.value` (the JSON:API module elides property names from single-property fields).');
    $normalized = [
      'promote.value' => '',
    ];
    $this->normalizer->denormalize($normalized, Filter::class, NULL, [
      'entity_type_id' => 'node',
      'bundle' => 'painting',
    ]);
  }

  /**
   * @covers ::queryCondition
   */
  public function testQueryCondition() {
    // Can't use a data provider because we need access to the container.
    $data = $this->queryConditionData();

    $get_sql_query_for_entity_query = function ($entity_query) {
      // Expose parts of \Drupal\Core\Entity\Query\Sql\Query::execute().
      $o = new \ReflectionObject($entity_query);
      $m1 = $o->getMethod('prepare');
      $m1->setAccessible(TRUE);
      $m2 = $o->getMethod('compile');
      $m2->setAccessible(TRUE);

      // The private property computed by the two previous private calls, whose
      // value we need to inspect.
      $p = $o->getProperty('sqlQuery');
      $p->setAccessible(TRUE);

      $m1->invoke($entity_query);
      $m2->invoke($entity_query);
      return (string) $p->getValue($entity_query);
    };

    foreach ($data as $case) {
      $normalized = $case[0];
      $expected_query = $case[1];
      // Denormalize the test filter into the object we want to test.
      $filter = $this->normalizer->denormalize($normalized, Filter::class, NULL, [
        'entity_type_id' => 'node',
        'bundle' => 'painting',
      ]);

      $query = $this->nodeStorage->getQuery();

      // Get the query condition parsed from the input.
      $condition = $filter->queryCondition($query);

      // Apply it to the query.
      $query->condition($condition);

      // Verify the SQL query is exactly the same.
      $expected_sql_query = $get_sql_query_for_entity_query($expected_query);
      $actual_sql_query = $get_sql_query_for_entity_query($query);
      $this->assertSame($expected_sql_query, $actual_sql_query);

      // Compare the results.
      $this->assertEquals($expected_query->execute(), $query->execute());
    }
  }

  /**
   * Simply provides test data to keep the actual test method tidy.
   */
  protected function queryConditionData() {
    // ((RED or CIRCLE) or (YELLOW and SQUARE))
    $query = $this->nodeStorage->getQuery();

    $or_group = $query->orConditionGroup();

    $nested_or_group = $query->orConditionGroup();
    $nested_or_group->condition('colors', 'red', 'CONTAINS');
    $nested_or_group->condition('shapes', 'circle', 'CONTAINS');
    $or_group->condition($nested_or_group);

    $nested_and_group = $query->andConditionGroup();
    $nested_and_group->condition('colors', 'yellow', 'CONTAINS');
    $nested_and_group->condition('shapes', 'square', 'CONTAINS');
    $nested_and_group->notExists('photo.alt');
    $or_group->condition($nested_and_group);

    $query->condition($or_group);

    return [
      [
        [
          'or-group' => ['group' => ['conjunction' => 'OR']],
          'nested-or-group' => ['group' => ['conjunction' => 'OR', 'memberOf' => 'or-group']],
          'nested-and-group' => ['group' => ['conjunction' => 'AND', 'memberOf' => 'or-group']],
          'condition-0' => [
            'condition' => [
              'path' => 'colors.value',
              'value' => 'red',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-or-group',
            ],
          ],
          'condition-1' => [
            'condition' => [
              'path' => 'shapes.value',
              'value' => 'circle',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-or-group',
            ],
          ],
          'condition-2' => [
            'condition' => [
              'path' => 'colors.value',
              'value' => 'yellow',
              'operator' =>
              'CONTAINS',
              'memberOf' => 'nested-and-group',
            ],
          ],
          'condition-3' => [
            'condition' => [
              'path' => 'shapes.value',
              'value' => 'square',
              'operator' => 'CONTAINS',
              'memberOf' => 'nested-and-group',
            ],
          ],
          'condition-4' => [
            'condition' => [
              'path' => 'photo.meta.alt',
              'operator' => 'IS NULL',
              'memberOf' => 'nested-and-group',
            ],
          ],
        ],
        $query,
      ],
    ];
  }

  /**
   * Sets up the schemas.
   */
  protected function setUpSchemas() {
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);

    $this->installSchema('user', []);
    foreach (['user', 'node'] as $entity_type_id) {
      $this->installEntitySchema($entity_type_id);
    }
  }

  /**
   * Creates a painting node type.
   */
  protected function savePaintingType() {
    NodeType::create([
      'type' => 'painting',
    ])->save();
    $this->createTextField(
      'node', 'painting',
      'colors', 'Colors',
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    $this->createTextField(
      'node', 'painting',
      'shapes', 'Shapes',
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
    $this->createImageField('photo', 'painting');
  }

  /**
   * Creates painting nodes.
   */
  protected function savePaintings($paintings) {
    foreach ($paintings as $painting) {
      Node::create(array_merge([
        'type' => 'painting',
      ], $painting))->save();
    }
  }

}
