<?php

namespace Drupal\Tests\ai_search\Unit;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_assistant_api\AiAssistantInterface;
use Drupal\ai_search\Plugin\AiAssistantAction\RagAction;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Tests that RagAction::getRagResults() passes the correct access bypass flag.
 *
 * Verifies that the RAG tool enforces access control by default and only
 * bypasses it when allow_access_bypass is explicitly TRUE.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\AiAssistantAction\RagAction
 * @group ai_search
 */
class RagActionGetRagResultsTest extends UnitTestCase {

  /**
   * The reflected getRagResults method.
   *
   * @var \ReflectionMethod
   */
  protected \ReflectionMethod $getRagResultsMethod;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->getRagResultsMethod = new \ReflectionMethod(RagAction::class, 'getRagResults');
    $this->getRagResultsMethod->setAccessible(TRUE);
  }

  /**
   * Builds a RagAction instance with a mock entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager mock.
   *
   * @return \Drupal\ai_search\Plugin\AiAssistantAction\RagAction
   *   A RagAction instance.
   */
  private function buildRagAction(EntityTypeManagerInterface $entityTypeManager): RagAction {
    $reflection = new \ReflectionClass(RagAction::class);
    $instance = $reflection->newInstanceWithoutConstructor();

    $reflectionClass = new \ReflectionObject($instance);
    while ($reflectionClass && !$reflectionClass->hasProperty('entityTypeManager')) {
      $reflectionClass = $reflectionClass->getParentClass() ?: NULL;
    }
    $reflectionProperty = $reflectionClass->getProperty('entityTypeManager');
    $reflectionProperty->setAccessible(TRUE);
    $reflectionProperty->setValue($instance, $entityTypeManager);

    // The assistant property is typed and cannot be left uninitialized.
    $reflectionClass = new \ReflectionObject($instance);
    while ($reflectionClass && !$reflectionClass->hasProperty('assistant')) {
      $reflectionClass = $reflectionClass->getParentClass() ?: NULL;
    }
    $reflectionProperty = $reflectionClass->getProperty('assistant');
    $reflectionProperty->setAccessible(TRUE);
    $reflectionProperty->setValue($instance, $this->createStub(AiAssistantInterface::class));

    return $instance;
  }

  /**
   * Builds a mock query that captures the search_api_bypass_access option.
   *
   * @param bool|null &$capturedBypass
   *   Reference to capture the bypass value passed to setOption().
   *
   * @return \PHPUnit\Framework\MockObject\MockObject|\Drupal\search_api\Query\QueryInterface
   *   A mock query.
   */
  private function buildQueryMock(mixed &$capturedBypass): QueryInterface {
    $query = $this->createMock(QueryInterface::class);
    $query->method('setOption')
      ->willReturnCallback(function (string $name, mixed $value) use (&$capturedBypass): void {
        if ($name === 'search_api_bypass_access') {
          $capturedBypass = $value;
        }
      });
    $query->method('execute')->willReturn($this->createStub(ResultSetInterface::class));
    return $query;
  }

  /**
   * Builds an entity type manager mock wired to return the given query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query the mock index will return.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   An entity type manager mock.
   */
  private function buildEntityTypeManagerMock(QueryInterface $query): EntityTypeManagerInterface {
    $index = $this->createMock(IndexInterface::class);
    $index->method('query')->willReturn($query);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturn($index);

    $entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $entityTypeManager->method('getStorage')->with('search_api_index')->willReturn($storage);

    return $entityTypeManager;
  }

  /**
   * Returns a base RAG database config array for getRagResults() calls.
   *
   * @param array $overrides
   *   Values to merge into the base config.
   *
   * @return array
   *   A RAG database config array.
   */
  private function ragDatabase(array $overrides = []): array {
    return $overrides + [
      'database' => 'test_index',
      'max_results' => 5,
      'output_mode' => 'chunks',
    ];
  }

  /**
   * @covers ::getRagResults
   */
  public function testAccessIsEnforcedByDefault(): void {
    $captured = NULL;
    $ragAction = $this->buildRagAction($this->buildEntityTypeManagerMock($this->buildQueryMock($captured)));

    $this->getRagResultsMethod->invoke($ragAction, $this->ragDatabase(['allow_access_bypass' => FALSE]));

    $this->assertFalse($captured, 'search_api_bypass_access must be FALSE when allow_access_bypass is FALSE.');
  }

  /**
   * @covers ::getRagResults
   */
  public function testAccessIsBypassedWhenFlagIsTrue(): void {
    $captured = NULL;
    $ragAction = $this->buildRagAction($this->buildEntityTypeManagerMock($this->buildQueryMock($captured)));

    $this->getRagResultsMethod->invoke($ragAction, $this->ragDatabase(['allow_access_bypass' => TRUE]));

    $this->assertTrue($captured, 'search_api_bypass_access must be TRUE when allow_access_bypass is TRUE.');
  }

  /**
   * @covers ::getRagResults
   */
  public function testMissingFlagDefaultsToEnforcement(): void {
    $captured = NULL;
    $ragAction = $this->buildRagAction($this->buildEntityTypeManagerMock($this->buildQueryMock($captured)));

    // No allow_access_bypass key — simulates old or minimal configuration.
    $this->getRagResultsMethod->invoke($ragAction, $this->ragDatabase());

    $this->assertFalse($captured, 'search_api_bypass_access must be FALSE when allow_access_bypass key is absent.');
  }

}
