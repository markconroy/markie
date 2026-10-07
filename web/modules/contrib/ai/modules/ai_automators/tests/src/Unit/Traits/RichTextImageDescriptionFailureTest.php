<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\Traits;

use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\Traits\RichTextImageDescriptionTrait;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests that un-analyzable images force the limit-exceeded (flag) state.
 *
 * Eligible images that cannot actually be analyzed (provider errors,
 * unreachable external URLs, empty responses) must be treated as unprocessed
 * so that moderation flags the content instead of approving it blind.
 *
 * @group ai_automators
 */
class RichTextImageDescriptionFailureTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    $container = new ContainerBuilder();
    $container->set('logger.factory', $factory);
    \Drupal::setContainer($container);
  }

  /**
   * A single eligible external-image candidate, within the configured limit.
   */
  protected function singleCandidateSummary(): array {
    return [
      'candidates' => [
        ['source_type' => 'external_url', 'source_url' => 'https://example.com/a.gif'],
      ],
      'encountered_count' => 1,
      'total_count' => 1,
      'processed_count' => 1,
      'skipped_count' => 0,
      'limit_exceeded' => FALSE,
      'has_unprocessed_images' => FALSE,
    ];
  }

  /**
   * A successful analysis stays un-flagged and returns the description.
   */
  public function testSuccessfulAnalysisDoesNotFlag(): void {
    $rule = $this->createRule($this->singleCandidateSummary(), 'A cat on a sofa.');

    $output = $rule->run(['include_image_descriptions' => TRUE, 'include_external_images' => TRUE]);

    $this->assertSame('1. A cat on a sofa.', $output);
    $this->assertFalse($rule->hasImageDescriptionLimitExceededPublic());
  }

  /**
   * A provider error during analysis forces the limit-exceeded state.
   */
  public function testProviderErrorForcesFlag(): void {
    $rule = $this->createRule($this->singleCandidateSummary(), '__throw__');

    $output = $rule->run(['include_image_descriptions' => TRUE, 'include_external_images' => TRUE]);

    $this->assertSame('', $output);
    $this->assertTrue($rule->hasImageDescriptionLimitExceededPublic());
    // 1 detected, 0 actually analyzed.
    $this->assertStringContainsString('only 0 of 1', $rule->getImageDescriptionLimitMessagePublic());
  }

  /**
   * An empty description (image sent but no usable output) forces the flag.
   */
  public function testEmptyDescriptionForcesFlag(): void {
    $rule = $this->createRule($this->singleCandidateSummary(), '');

    $output = $rule->run(['include_image_descriptions' => TRUE, 'include_external_images' => TRUE]);

    $this->assertSame('', $output);
    $this->assertTrue($rule->hasImageDescriptionLimitExceededPublic());
  }

  /**
   * A failed external fetch forces the flag.
   */
  public function testFailedFetchForcesFlag(): void {
    $rule = $this->createRule($this->singleCandidateSummary(), 'unused', FALSE);

    $output = $rule->run(['include_image_descriptions' => TRUE, 'include_external_images' => TRUE]);

    $this->assertSame('', $output);
    $this->assertTrue($rule->hasImageDescriptionLimitExceededPublic());
  }

  /**
   * Builds a testable rule with stubbed LLM and fetch seams.
   *
   * @param array<string,mixed> $summary
   *   The candidate summary the helper returns.
   * @param string $chatResult
   *   The chat description text, or '__throw__' to simulate a provider error.
   * @param bool $fetchSucceeds
   *   Whether the external fetch returns data.
   */
  protected function createRule(array $summary, string $chatResult, bool $fetchSucceeds = TRUE): object {
    $reflection = new \ReflectionClass(FailureTestableRule::class);
    /** @var \Drupal\Tests\ai_automators\Unit\Traits\FailureTestableRule $rule */
    $rule = $reflection->newInstanceWithoutConstructor();
    $rule->summary = $summary;
    $rule->chatResult = $chatResult;
    $rule->fetchSucceeds = $fetchSucceeds;
    $rule->entity = $this->createMock(ContentEntityInterface::class);
    $rule->setStringTranslation($this->getStringTranslationStub());
    return $rule;
  }

}

/**
 * Testable rule running the real generateImageDescriptionsFromRawContext().
 */
class FailureTestableRule extends RuleBase {

  use RichTextImageDescriptionTrait;

  /**
   * The candidate summary returned by the stubbed helper.
   *
   * @var array<string,mixed>
   */
  public array $summary = [];

  /**
   * The chat description text, or '__throw__' to simulate a provider error.
   *
   * @var string
   */
  public string $chatResult = '';

  /**
   * Whether the stubbed external fetch returns data.
   *
   * @var bool
   */
  public bool $fetchSucceeds = TRUE;

  /**
   * A stub entity.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface
   */
  public ContentEntityInterface $entity;

  /**
   * Runs the real description generation against a fixed raw context.
   */
  public function run(array $config): string {
    return $this->generateImageDescriptionsFromRawContext('<img src="https://example.com/a.gif">', $this->entity, $config, 0);
  }

  /**
   * Exposes the protected flag for assertions.
   */
  public function hasImageDescriptionLimitExceededPublic(): bool {
    return $this->hasImageDescriptionLimitExceeded();
  }

  /**
   * Exposes the protected limit message for assertions.
   */
  public function getImageDescriptionLimitMessagePublic(): string {
    return $this->getImageDescriptionLimitMessage();
  }

  /**
   * {@inheritdoc}
   */
  protected function getRichTextImageDescriptionService() {
    return new class($this->summary, $this->fetchSucceeds) {

      /**
       * Constructs the stub service.
       */
      public function __construct(private array $summary, private bool $fetchSucceeds) {}

      /**
       * Mimics rich-text image processing behavior for tests.
       */
      public function processRawContext(
        string $rawContext,
        bool $includeExternal,
        int $maxImages,
        int $delta,
        string $prompt,
        array $metadataContext,
        callable $describeImage,
      ): array {
        $summary = $this->summary;
        $candidates = $summary['candidates'] ?? [];
        $encounteredCount = (int) ($summary['encountered_count'] ?? 0);
        $totalCount = (int) ($summary['total_count'] ?? 0);
        $processedCount = (int) ($summary['processed_count'] ?? 0);
        $skippedCount = (int) ($summary['skipped_count'] ?? 0);
        $limitExceeded = !empty($summary['limit_exceeded']) || !empty($summary['has_unprocessed_images']);
        $lines = [];
        $failedCount = 0;

        foreach ($candidates as $candidate) {
          if (($candidate['source_type'] ?? '') === 'external_url' && !$this->fetchSucceeds) {
            $failedCount++;
            continue;
          }
          try {
            $description = trim((string) $describeImage(new ImageFile(), $candidate, $prompt));
            if ($description === '') {
              $failedCount++;
              continue;
            }
            $line = count($lines) + 1;
            $lines[] = $line . '. ' . $description;
          }
          catch (\Throwable) {
            $failedCount++;
          }
        }

        if ($failedCount > 0) {
          $processedCount = max(0, $processedCount - $failedCount);
          $skippedCount += $failedCount;
          $limitExceeded = TRUE;
        }

        return [
          'descriptions' => implode("\n", $lines),
          'metadata' => [],
          'encountered_count' => $encounteredCount,
          'total_count' => $totalCount,
          'processed_count' => $processedCount,
          'skipped_count' => $skippedCount,
          'limit_exceeded' => $limitExceeded,
        ];
      }

    };
  }

  /**
   * {@inheritdoc}
   */
  public function prepareLlmInstance($operationType, array &$automatorConfig) {
    return new class($this->chatResult) {

      /**
       * Constructs the stub chat instance.
       */
      public function __construct(private string $result) {}

      /**
       * Returns a normalized response, or throws to simulate provider error.
       */
      public function chat($input, $model, $tags) {
        if ($this->result === '__throw__') {
          throw new \RuntimeException('provider failure');
        }
        return new class($this->result) {

          /**
           * Constructs the stub response wrapper.
           */
          public function __construct(private string $result) {}

          /**
           * Returns the normalized message.
           */
          public function getNormalized() {
            return new class($this->result) {

              /**
               * Constructs the stub message.
               */
              public function __construct(private string $result) {}

              /**
               * Returns the description text.
               */
              public function getText(): string {
                return $this->result;
              }

            };
          }

        };
      }

    };
  }

  /**
   * {@inheritdoc}
   */
  protected function getModel(array &$automatorConfig): string {
    return 'fake-model';
  }

  /**
   * {@inheritdoc}
   */
  public function getTags(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
  }

  /**
   * {@inheritdoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return TRUE;
  }

}
