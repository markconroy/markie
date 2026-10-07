<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_search\EmbeddingStrategyInterface;
use Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase;
use Drupal\ai\Enum\EmbeddingStrategyCapability;
use Drupal\ai\AiVdbProviderInterface;
use Drupal\search_api\IndexInterface;
use Drupal\test_embedding_strategy\Plugin\EmbeddingStrategy\TestCustomEmbeddingStrategy;

/**
 * Verifies BC safety of extending EmbeddingBase on the 1.0.x branch.
 *
 * These tests MUST remain green through the entire backport. Their purpose is
 * to prove that third-party code extending EmbeddingBase with the 1.0.x
 * six-parameter getEmbedding() signature continues to work after each change.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
 * @group ai_search
 */
class EmbeddingBaseExtensionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_search',
    'ai',
    'search_api',
    'key',
    'test_ai_provider_mysql',
    'test_embedding_strategy',
  ];

  /**
   * The embedding strategy plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai_search']);
    $this->pluginManager = $this->container->get('ai_search.embedding_strategy');
  }

  /**
   * The custom plugin loads from the manager and is the expected class.
   *
   * @covers ::__construct
   */
  public function testCustomStrategyLoadsFromPluginManager(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $this->assertInstanceOf(TestCustomEmbeddingStrategy::class, $strategy);
    $this->assertInstanceOf(EmbeddingBase::class, $strategy);
  }

  /**
   * The custom plugin satisfies EmbeddingStrategyInterface.
   *
   * PHP enforces this at class-definition time; the test makes it explicit and
   * gives a readable failure if the interface ever stops being satisfied.
   *
   * @covers ::getEmbedding
   */
  public function testCustomStrategyImplementsEmbeddingStrategyInterface(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $this->assertInstanceOf(EmbeddingStrategyInterface::class, $strategy);
  }

  /**
   * Init() still accepts the legacy three-parameter signature at runtime.
   *
   * @covers ::init
   */
  public function testInitAcceptsThreeLegacyParams(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');

    // Must not throw a TypeError — calling with the exact 1.0.x signature.
    $strategy->init('test_mysql_provider__test_model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
    ]);

    $method = new \ReflectionMethod($strategy, 'init');
    $params = $method->getParameters();
    $this->assertCount(3, $params);
    $this->assertSame('embedding_engine', $params[0]->getName());
    $this->assertSame('chat_model', $params[1]->getName());
    $this->assertSame('configuration', $params[2]->getName());
  }

  /**
   * GetEmbedding() still has the six-parameter signature on EmbeddingBase.
   *
   * Uses reflection so this test does not need a live embeddings endpoint.
   *
   * @covers ::getEmbedding
   */
  public function testGetEmbeddingSignatureHasSixParams(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $method = new \ReflectionMethod($strategy, 'getEmbedding');
    $params = $method->getParameters();

    $this->assertCount(6, $params, 'getEmbedding() must keep its six-parameter signature for BC.');
    $this->assertSame('embedding_engine', $params[0]->getName());
    $this->assertSame('chat_model', $params[1]->getName());
    $this->assertSame('configuration', $params[2]->getName());
    $this->assertSame('fields', $params[3]->getName());
    $this->assertSame('search_api_item', $params[4]->getName());
    $this->assertSame('index', $params[5]->getName());
  }

  /**
   * GroupFieldData() still works when called with only two arguments.
   *
   * @covers ::groupFieldData
   */
  public function testGroupFieldDataWorksWithTwoParams(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $strategy->init('test_mysql_provider__test_model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
    ]);

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('test_extension_index');

    $result = $strategy->groupFieldData([], $index);

    $this->assertIsArray($result);
    $this->assertGreaterThanOrEqual(3, count($result));
    [$title, $contextual_content, $main_content] = $result;
    $this->assertIsString($title);
    $this->assertIsString($contextual_content);
    $this->assertIsString($main_content);
  }

  /**
   * The protected getChunks() method retains its expected signature.
   *
   * This is the internal chunking method. It must NOT be renamed; doing so
   * would silently bypass any subclass override.
   *
   * @covers ::getChunks
   */
  public function testProtectedGetChunksSignatureIsStable(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $method = new \ReflectionMethod($strategy, 'getChunks');
    $params = $method->getParameters();

    $this->assertTrue($method->isProtected(), 'getChunks() must remain protected.');
    $this->assertCount(5, $params);
    $this->assertSame('title', $params[0]->getName());
    $this->assertSame('main_content', $params[1]->getName());
    $this->assertSame('contextual_content', $params[2]->getName());
    $this->assertSame('title_in_contextual', $params[3]->getName());
    $this->assertSame('index', $params[4]->getName());
    $this->assertTrue($params[3]->isOptional(), '$title_in_contextual must have a default.');
    $this->assertTrue($params[4]->isOptional(), '$index must have a default.');
  }

  /**
   * The protected prepareChunkText() method retains its expected signature.
   *
   * @covers ::prepareChunkText
   */
  public function testProtectedPrepareChunkTextSignatureIsStable(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $method = new \ReflectionMethod($strategy, 'prepareChunkText');
    $params = $method->getParameters();

    $this->assertTrue($method->isProtected(), 'prepareChunkText() must remain protected.');
    $this->assertCount(3, $params, 'prepareChunkText() must keep its three-parameter signature for BC.');
    $this->assertSame('title', $params[0]->getName());
    $this->assertSame('main_chunk', $params[1]->getName());
    $this->assertSame('contextual_chunk', $params[2]->getName());
  }

  /**
   * BuildBaseMetadata() returns an array for an empty field set.
   *
   * @covers ::buildBaseMetadata
   */
  public function testBuildBaseMetadataIsCallable(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('test_extension_index');

    // Mock config so getRawData() returns a valid indexing_options structure.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn(['indexing_options' => []]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $reflection = new \ReflectionClass(EmbeddingBase::class);
    $property = $reflection->getParentClass()->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($strategy, $configFactory);

    $metadata = $strategy->buildBaseMetadata([], $index);
    $this->assertIsArray($metadata);
  }

  /**
   * AddContentToMetadata() adds or omits the content key based on config.
   *
   * @covers ::addContentToMetadata
   */
  public function testAddContentToMetadataIsCallable(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');

    $index = $this->createMock(IndexInterface::class);
    $index->method('id')->willReturn('test_extension_index');

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn([]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $reflection = new \ReflectionClass(EmbeddingBase::class);
    $property = $reflection->getParentClass()->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($strategy, $configFactory);

    $result = $strategy->addContentToMetadata(['key' => 'value'], 'chunk text', $index);
    $this->assertIsArray($result);
    $this->assertArrayHasKey('content', $result);
    $this->assertSame('chunk text', $result['content']);
  }

  /**
   * Fits() returns a boolean — interface method, must remain present.
   *
   * @covers ::fits
   */
  public function testFitsReturnsBoolean(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $vdb = $this->createMock(AiVdbProviderInterface::class);
    $this->assertIsBool($strategy->fits($vdb));
  }

  /**
   * Supports() returns a boolean — interface method, must remain present.
   *
   * @covers ::supports
   */
  public function testSupportsReturnsBoolean(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $capability = EmbeddingStrategyCapability::MultipleMainContent;
    $this->assertIsBool($strategy->supports($capability));
  }

  /**
   * Custom default configuration values merge with the parent correctly.
   *
   * @covers ::getDefaultConfigurationValues
   */
  public function testCustomDefaultConfigurationValuesAreMerged(): void {
    $strategy = $this->pluginManager->createInstance('test_custom');
    $defaults = $strategy->getDefaultConfigurationValues();

    $this->assertArrayHasKey('chunk_size', $defaults);
    $this->assertArrayHasKey('chunk_min_overlap', $defaults);
    $this->assertArrayHasKey('custom_option', $defaults);
    $this->assertTrue($defaults['custom_option']);
  }

}
