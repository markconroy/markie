<?php

namespace Drupal\Tests\ai_search\Kernel;

use Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\IndexInterface;

/**
 * Tests the chunking logic of the EmbeddingBase strategy plugin.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\EmbeddingStrategy\EmbeddingBase
 * @group ai_search
 */
class EmbeddingBaseGetChunksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai_search',
    'ai',
    'search_api',
    'key',
    'test_ai_provider_mysql',
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['ai_search']);

    // Get the plugin manager from the container and create an instance of the
    // plugin we want to test.
    $manager = $this->container->get('ai_search.embedding_strategy');
    $this->embeddingStrategy = $manager->createInstance('contextual_chunks');

    // Mock the Search API Index.
    $this->index = $this->createMock(IndexInterface::class);
    $this->index->method('id')->willReturn('test_index');

    // Initialize the plugin with a default configuration.
    $this->embeddingStrategy->init('test_mysql_provider__test_model', 'gpt-3.5', [
      'chunk_size' => 250,
      'chunk_min_overlap' => 25,
      'contextual_content_max_percentage' => 30,
    ]);
  }

  /**
   * A helper method to make a protected method accessible for testing.
   *
   * @param string $methodName
   *   The name of the protected method to make accessible.
   *
   * @return \ReflectionMethod
   *   The ReflectionMethod object, which can be invoked.
   */
  protected function getProtectedMethod(string $methodName): \ReflectionMethod {
    $class = new \ReflectionClass(EmbeddingBase::class);
    $method = $class->getMethod($methodName);
    $method->setAccessible(TRUE);
    return $method;
  }

  /**
   * Provides test cases for the getChunks() method.
   *
   * @return array[]
   *   An array of test cases.
   */
  public static function chunkingDataProvider(): array {
    // Generate long text strings to force chunking logic.
    $long_main = 'Drupal is a free and open-source web content management system (CMS) written in PHP and distributed under the GNU General Public License. Drupal provides a back-end framework for at least 14% of the top 10,000 websites worldwide – ranging from personal blogs to corporate, political, and government sites. Systems also use Drupal for knowledge management and for business collaboration. The standard release of Drupal, known as Drupal core, contains basic features common to most content management systems. These include user account registration and maintenance, menu management, RSS feeds, taxonomy, page layout customization, and system administration. The Drupal core installation can serve as a simple website, a single- or multi-user blog, an Internet forum, or a community website providing for user-generated content. As of March 2024, the Drupal community is composed of more than 1.39 million members, including 124,000 users actively contributing, resulting in more than 52,000 free modules that extend and customize Drupal functionality, over 3,000 free themes that change the look and feel of Drupal, and at least 1,400 free distributions that allow users to quickly and easily set up a complex, use-specific Drupal in fewer steps.';
    $long_context = 'The context for this content is the history of open-source software development and its impact on modern web technologies. We are examining the role of community-driven projects in creating robust and scalable platforms. This analysis includes a deep dive into the governance models of projects like Drupal, the economic impact of their ecosystems, and the technical innovations they have pioneered over the years. We consider contributions from both individual developers and corporate sponsors, and how that dynamic shapes the evolution of the platform.';

    return [
      'all content fits in a single chunk' => [
        'all content fits in a single chunk',
        'Short Title',
        'This is the main content and it is short enough to fit.',
        'Context: short.',
        1,
      ],
      'main content needs chunking, context is small' => [
        'main content needs chunking, context is small',
        'A Title About Drupal',
        $long_main,
        'Context: Open Source CMS.',
        2,
      ],
      'both main and contextual content need chunking' => [
        'both main and contextual content need chunking',
        'The History and Impact of Open Source',
        $long_main,
        $long_context,
        4,
      ],
    ];
  }

  /**
   * Tests the chunking logic under various conditions.
   *
   * @covers ::getChunks
   * @covers ::prepareChunkText
   * @dataProvider chunkingDataProvider
   */
  public function testGetChunks(string $case_id, string $title, string $main_content, string $contextual_content, int $expected_chunks) {
    // Mock the config factory to return a specific config for this test case.
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('getRawData')->willReturn([]);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('ai_search.index.test_index')->willReturn($config);

    // Use reflection to inject the mocked config factory into the plugin.
    $reflection = new \ReflectionClass(EmbeddingBase::class);
    $property = $reflection->getParentClass()->getProperty('configFactory');
    $property->setAccessible(TRUE);
    $property->setValue($this->embeddingStrategy, $configFactory);

    // Get the protected getChunks method and invoke it with test data.
    $getChunksMethod = $this->getProtectedMethod('getChunks');
    $chunks = $getChunksMethod->invoke(
      $this->embeddingStrategy,
      $title,
      $main_content,
      $contextual_content,
      FALSE,
      $this->index
    );

    $tokenizer = \Drupal::service('ai.tokenizer');
    $tokenizer->setModel('gpt-3.5');

    // Check chunk counts and individual chunk sizes.
    $this->assertCount($expected_chunks, $chunks);
    foreach ($chunks as $chunk) {
      $this->assertLessThanOrEqual(250, $tokenizer->countTokens($chunk), 'Chunk size should not significantly exceed the target size plus overlap.');
    }

    // Run the specific assertions based on the test case.
    switch ($case_id) {
      case 'all content fits in a single chunk':
        $this->assertStringContainsString('# SHORT TITLE', $chunks[0]);
        $this->assertStringContainsString('This is the main content', $chunks[0]);
        $this->assertStringContainsString('Context: short.', $chunks[0]);
        break;

      case 'main content needs chunking, context is small':
        $this->assertStringContainsString('# A TITLE ABOUT DRUPAL', $chunks[0]);
        $this->assertStringContainsString('Context: Open Source CMS.', $chunks[0]);
        $this->assertStringContainsString('back-end framework', $chunks[0]);
        $this->assertStringContainsString('# A TITLE ABOUT DRUPAL', $chunks[1]);
        $this->assertStringContainsString('Context: Open Source CMS.', $chunks[1]);
        $this->assertStringContainsString('customize Drupal functionality', $chunks[1]);
        $this->assertNotEquals($chunks[0], $chunks[1]);
        break;

      case 'both main and contextual content need chunking':
        $this->assertStringContainsString('# THE HISTORY AND IMPACT OF OPEN SOURCE', $chunks[0]);
        $this->assertStringContainsString('Drupal is a free and open-source web content', $chunks[0]);
        $this->assertStringContainsString('The context for this content is the history', $chunks[0]);
        break;
    }
  }

}
