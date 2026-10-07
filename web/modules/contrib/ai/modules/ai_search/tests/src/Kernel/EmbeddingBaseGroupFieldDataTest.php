<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\Plugin\DataType\EntityAdapter;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Item\Item;

/**
 * Tests that groupFieldData() correctly detects the title field.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
 * @group ai_search
 */
class EmbeddingBaseGroupFieldDataTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_search',
    'ai',
    'search_api',
    'key',
    'test_ai_provider_mysql',
    'entity_test',
    'field',
    'text',
    'filter',
    'user',
    'system',
  ];

  /**
   * The embedding strategy plugin instance under test.
   *
   * @var \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
   */
  protected $embeddingStrategy;

  /**
   * A mock of the Search API Index used in tests.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * A mock of the Search API datasource used in tests.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject|\Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    $this->installConfig(['ai_search']);

    $manager = $this->container->get('ai_search.embedding_strategy');
    $this->embeddingStrategy = $manager->createInstance('contextual_chunks');

    $this->datasource = $this->createMock(DatasourceInterface::class);
    $this->datasource->method('getEntityTypeId')->willReturn('entity_test');

    $this->index = $this->createMock(IndexInterface::class);
    $this->index->method('id')->willReturn('test_index');
    $this->index->method('getDatasource')->with('entity:entity_test')->willReturn($this->datasource);

    $this->embeddingStrategy->init('test_mysql_provider__test_model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
      'contextual_content_max_percentage' => 30,
    ]);
  }

  /**
   * Builds a Search API field attached to the entity_test datasource.
   *
   * @param string $field_identifier
   *   The Search API field identifier (machine name), which is configurable
   *   by the site builder and may differ from the entity property path.
   * @param string $property_path
   *   The entity property path the field maps to.
   * @param string $value
   *   The field value.
   *
   * @return \Drupal\search_api\Item\Field
   *   The constructed field.
   */
  protected function buildField(string $field_identifier, string $property_path, string $value): Field {
    $data_definition = $this->createMock(DataDefinitionInterface::class);
    $data_definition->method('getSettings')->willReturn([]);
    $data_definition->method('getDataType')->willReturn('string');

    $field = new Field($this->index, $field_identifier);
    $field->setDatasourceId('entity:entity_test');
    $field->setPropertyPath($property_path);
    $field->setLabel('Title');
    $field->setType('string');
    $field->setDataDefinition($data_definition);
    $field->setValues([$value]);

    return $field;
  }

  /**
   * Injects a mocked config factory returning the given indexing options.
   *
   * @param array $indexing_options
   *   The indexing options, keyed by field identifier.
   */
  protected function setIndexingOptions(array $indexing_options): void {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn(['indexing_options' => $indexing_options]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('ai_search.index.test_index')->willReturn($config);

    $reflection = new \ReflectionClass(EmbeddingBase::class);
    $property = $reflection->getParentClass()->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($this->embeddingStrategy, $configFactory);
  }

  /**
   * Provides test cases for groupFieldData().
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function titleFieldDataProvider(): array {
    return [
      'field identifier matches the label property path' => [
        'title',
        'name',
        TRUE,
      ],
      'field identifier differs from the label property path' => [
        'entity_title',
        'name',
        TRUE,
      ],
      'property path does not match the label key' => [
        'entity_title',
        'body',
        FALSE,
      ],
    ];
  }

  /**
   * Tests that the title field is detected regardless of its machine name.
   *
   * @covers ::groupFieldData
   * @dataProvider titleFieldDataProvider
   */
  public function testTitleFieldDetection(string $field_identifier, string $property_path, bool $expected_title_detected): void {
    $this->setIndexingOptions([
      $field_identifier => ['indexing_option' => 'contextual_content'],
    ]);

    $title_field = $this->buildField($field_identifier, $property_path, 'My Custom Entity Title');

    [$title, $contextual_content, $main_content] = $this->embeddingStrategy->groupFieldData([$title_field], $this->index);

    $this->assertSame($expected_title_detected ? 'My Custom Entity Title' : '', $title);
    $this->assertStringContainsString('My Custom Entity Title', $contextual_content);
    $this->assertSame('', $main_content);
  }

  /**
   * Tests that the title is not duplicated when the field identifier differs.
   *
   * When the title field is explicitly indexed as Contextual Content,
   * isTitleInContextual() must suppress the automatic "# TITLE" heading so
   * the title is not duplicated in the resulting chunks — regardless of the
   * Search API field's configured machine name.
   *
   * @covers ::isTitleInContextual
   * @covers ::computeItemChunks
   */
  public function testTitleNotDuplicatedWhenFieldIdentifierDiffersFromPropertyPath(): void {
    $this->setIndexingOptions([
      'entity_title' => ['indexing_option' => 'contextual_content'],
    ]);

    $title_field = $this->buildField('entity_title', 'name', 'My Custom Entity Title');

    $entity = EntityTest::create(['name' => 'My Custom Entity Title']);
    $item = new Item($this->index, 'entity_test/1', $this->datasource);
    $item->setOriginalObject(EntityAdapter::createFromEntity($entity));

    $chunks = $this->embeddingStrategy->computeItemChunks(
      'test_mysql_provider__test_model',
      [
        'chunk_size' => 250,
        'chunk_min_overlap' => 25,
        'contextual_content_max_percentage' => 30,
      ],
      [$title_field],
      $item,
      $this->index,
    );

    $this->assertNotEmpty($chunks);
    foreach ($chunks as $chunk) {
      $this->assertStringContainsString('Title: My Custom Entity Title', $chunk);
      $this->assertStringNotContainsString('# MY CUSTOM ENTITY TITLE', $chunk);
    }
  }

}
