<?php

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_translate\EntityTranslationOrchestrator;
use Drupal\ai_translate\TextExtractorInterface;
use Drupal\ai_translate\TextTranslatorInterface;
use Drupal\ai_translate\TranslationException;
use Drupal\content_translation\ContentTranslationManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @coversDefaultClass \Drupal\ai_translate\EntityTranslationOrchestrator
 * @group ai_translate
 */
class EntityTranslationOrchestratorTest extends UnitTestCase {

  /**
   * The text extractor mock.
   *
   * @var \Drupal\ai_translate\TextExtractorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $textExtractor;

  /**
   * The text translator mock.
   *
   * @var \Drupal\ai_translate\TextTranslatorInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $textTranslator;

  /**
   * The language manager mock.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $languageManager;

  /**
   * The config factory mock.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configFactory;

  /**
   * The logger factory mock.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $loggerFactory;

  /**
   * The entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The content translation manager mock.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $contentTranslationManager;

  /**
   * The service under test.
   */
  protected EntityTranslationOrchestrator $orchestrator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->textExtractor = $this->createMock(TextExtractorInterface::class);
    $this->textTranslator = $this->createMock(TextTranslatorInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->contentTranslationManager = $this->createMock(ContentTranslationManagerInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->with('ai_translate')->willReturn($logger);

    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->with('translation_status')->willReturn('keep_original');
    $this->configFactory->method('get')->with('ai_translate.settings')->willReturn($config);

    $this->orchestrator = new EntityTranslationOrchestrator(
      $this->textExtractor,
      $this->textTranslator,
      $this->languageManager,
      $this->configFactory,
      $this->loggerFactory,
      $this->entityTypeManager,
      $this->contentTranslationManager,
    );
    $this->orchestrator->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * @covers ::translateEntity
   */
  public function testTranslateEntityReturnsExistingResult(): void {
    $sourceLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('language')->willReturn($sourceLanguage);
    $entity->method('hasTranslation')->willReturnMap([
      ['fr', TRUE],
      ['en', FALSE],
    ]);

    $result = $this->orchestrator->translateEntity($entity, 'en', 'fr');

    $this->assertFalse($result->isSuccess());
    $this->assertTrue($result->translationExists());
    $this->assertSame('Translation already exists.', (string) $result->getMessage());
  }

  /**
   * Tests entity handling using the metadata supplied by field extractors.
   *
   * @dataProvider translatedValueProvider
   * @covers ::translateTextMetadataItem
   */
  #[DataProvider('translatedValueProvider')]
  public function testTranslatedValues(array $metadata, string $response, string $expected): void {
    $sourceLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']);
    $targetLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'fr']);
    $column = $metadata['_columns'][0];

    $this->textTranslator->expects($this->once())
      ->method('translateContent')
      ->with($metadata[$column], $targetLanguage, $sourceLanguage)
      ->willReturn($response);

    $translated = $this->orchestrator->translateTextMetadataItem($metadata, $sourceLanguage, $targetLanguage);
    $this->assertSame($expected, $translated['translated'][$column]);
    unset($translated['translated']);
    $this->assertSame($metadata, $translated);
  }

  /**
   * Provides plain-text columns and a formatted field with ambiguous HTML.
   */
  public static function translatedValueProvider(): iterable {
    $probes = [
      'named entity is decoded' => ['Tom &amp; Jerry', 'Tom & Jerry'],
      'escaped tag is decoded' => ['&lt;p&gt;Hello&lt;/p&gt;', '<p>Hello</p>'],
    ];
    $columns = [
      'string' => ['field_string', 'value'],
      'string_long' => ['field_string_long', 'value'],
      'node title' => ['title', 'value'],
      'image alt' => ['field_image', 'alt'],
      'image title' => ['field_image', 'title'],
      'link title' => ['field_link', 'title'],
      'file description' => ['field_file', 'description'],
    ];
    foreach ($columns as $category => [$field, $column]) {
      foreach ($probes as $case => [$response, $expected]) {
        yield "$category: $case" => [
          [
            '_columns' => [$column],
            $column => 'Source text',
            'field_name' => $field,
          ],
          $response,
          $expected,
        ];
      }
    }

    // A formatted response is decoded only when it holds "&lt;" and no real
    // "<": that means the whole document round-tripped through the provider
    // escaped (issue #3476038). A real "<" means the markup is genuine, so
    // the response is preserved verbatim.
    yield 'formatted field preserves html entities' => [
      ['_columns' => ['value'], 'value' => 'Source', 'format' => 'basic_html'],
      'Bonjour &amp; monde',
      'Bonjour &amp; monde',
    ];
    yield 'formatted field decodes a fully escaped value' => [
      ['_columns' => ['value'], 'value' => 'Source', 'format' => 'basic_html'],
      '&lt;p&gt;Bonjour&lt;/p&gt;',
      '<p>Bonjour</p>',
    ];
  }

  /**
   * @covers ::translateTextMetadataItem
   */
  public function testTranslateTextMetadataItemReturnsNullOnFailure(): void {
    $sourceLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']);
    $targetLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'fr']);

    $singleField = [
      '_columns' => ['value'],
      'value' => 'Hello',
      'parents' => ['body', 0],
      'field_name' => 'body',
    ];

    $this->textTranslator->expects($this->once())
      ->method('translateContent')
      ->willThrowException(new TranslationException('Boom'));

    $this->assertNull($this->orchestrator->translateTextMetadataItem($singleField, $sourceLanguage, $targetLanguage));
  }

  /**
   * @covers ::translateEntity
   * @covers ::saveTranslatedEntity
   */
  public function testTranslateEntityCreatesAndSavesTranslation(): void {
    $sourceLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']);
    $targetLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'fr']);

    $this->languageManager->method('getLanguage')->willReturnMap([
      ['en', $sourceLanguage],
      ['fr', $targetLanguage],
    ]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $translation = $this->createMock(ContentEntityInterface::class);

    $entity->method('language')->willReturn($sourceLanguage);
    $entity->method('hasTranslation')->willReturn(FALSE);
    $entity->method('toArray')->willReturn(['body' => [['value' => 'Hello']]]);
    $entity->expects($this->once())
      ->method('addTranslation')
      ->with('fr', ['body' => [['value' => 'Hello']]])
      ->willReturn($translation);

    $metadata = [[
      '_columns' => ['value'],
      'value' => 'Hello',
      'parents' => ['body', 0],
      'field_name' => 'body',
    ],
    ];

    $this->textExtractor->expects($this->once())
      ->method('extractTextMetadata')
      ->with($entity)
      ->willReturn($metadata);

    $this->textTranslator->expects($this->once())
      ->method('translateContent')
      ->with('Hello', $targetLanguage, $sourceLanguage)
      ->willReturn('Bonjour');

    $expectedMetadata = [[
      '_columns' => ['value'],
      'value' => 'Hello',
      'parents' => ['body', 0],
      'field_name' => 'body',
      'translated' => ['value' => 'Bonjour'],
    ],
    ];

    $this->textExtractor->expects($this->once())
      ->method('insertTextMetadata')
      ->with($translation, $expectedMetadata);

    $translation->expects($this->once())->method('save');

    $result = $this->orchestrator->translateEntity($entity, 'en', 'fr');

    $this->assertTrue($result->isSuccess());
    $this->assertSame($translation, $result->getTranslatedEntity());
    $this->assertSame('Content translated successfully.', (string) $result->getMessage());
    $this->assertSame([], $result->getFailures());
  }

  /**
   * @covers ::translateEntity
   */
  public function testTranslateEntityFailsForInvalidTargetLanguage(): void {
    $sourceLanguage = $this->createConfiguredMock(LanguageInterface::class, ['getId' => 'en']);
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('language')->willReturn($sourceLanguage);
    $entity->method('hasTranslation')->willReturn(FALSE);

    $this->languageManager->method('getLanguage')->willReturnMap([
      ['en', $sourceLanguage],
      ['fr', NULL],
    ]);

    $result = $this->orchestrator->translateEntity($entity, 'en', 'fr');

    $this->assertFalse($result->isSuccess());
    $this->assertSame('Invalid target language.', (string) $result->getMessage());
  }

}
