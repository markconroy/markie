<?php

namespace Drupal\Tests\ai_search\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ai_search\Plugin\search_api\processor\DatabaseBoostByAiSearch;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\ConditionInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Tests applyParentConditions() from BoostByAiSearchBase.
 *
 * Uses DatabaseBoostByAiSearch as the concrete subclass under test.
 *
 * @coversDefaultClass \Drupal\ai_search\Plugin\search_api\processor\BoostByAiSearchBase
 * @group ai_search
 */
class BoostByAiSearchBaseTest extends UnitTestCase {

  /**
   * The processor instance under test.
   *
   * @var \Drupal\ai_search\Plugin\search_api\processor\DatabaseBoostByAiSearch
   */
  protected DatabaseBoostByAiSearch $processor;

  /**
   * The reflected applyParentConditions method.
   */
  protected \ReflectionMethod $applyParentConditionsMethod;

  /**
   * The reflected configuration property.
   */
  protected \ReflectionProperty $configurationProperty;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $reflection = new \ReflectionClass(DatabaseBoostByAiSearch::class);
    $this->processor = $reflection->newInstanceWithoutConstructor();

    $this->applyParentConditionsMethod = $reflection->getMethod('applyParentConditions');

    // The configuration property is declared in PluginBase; walk up to find it.
    $r = new \ReflectionObject($this->processor);
    while ($r) {
      if ($r->hasProperty('configuration')) {
        $this->configurationProperty = $r->getProperty('configuration');
        break;
      }
      $r = $r->getParentClass() ?: NULL;
    }
  }

  /**
   * Sets the pass_conditions_fields configuration on the processor.
   */
  private function setPassConditionsFields(array $fields): void {
    $this->configurationProperty->setValue($this->processor, [
      'pass_conditions_fields' => $fields,
    ]);
  }

  /**
   * Builds a parent query mock returning the given condition list.
   *
   * @param array $conditions
   *   ConditionInterface or ConditionGroupInterface objects.
   */
  private function buildParentQuery(array $conditions): QueryInterface {
    $condition_group = $this->createMock(ConditionGroupInterface::class);
    $condition_group->method('getConditions')->willReturn($conditions);

    $parent_query = $this->createMock(QueryInterface::class);
    $parent_query->method('getConditionGroup')->willReturn($condition_group);
    return $parent_query;
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testNoConditionsAddedWhenNoFieldsConfigured(): void {
    $this->setPassConditionsFields([]);

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->never())->method('addCondition');

    $parent_query = $this->createMock(QueryInterface::class);
    // getConditionGroup must not even be reached when the field list is empty.
    $parent_query->expects($this->never())->method('getConditionGroup');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $parent_query],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testMatchingConditionIsCopiedToAiQuery(): void {
    $this->setPassConditionsFields(['type' => 'type']);

    $condition = $this->createMock(ConditionInterface::class);
    $condition->method('getField')->willReturn('type');
    $condition->method('getValue')->willReturn('article');
    $condition->method('getOperator')->willReturn('=');

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->once())
      ->method('addCondition')
      ->with('type', 'article', '=');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$condition])],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testNonConfiguredFieldConditionIsIgnored(): void {
    $this->setPassConditionsFields(['type' => 'type']);

    $condition = $this->createMock(ConditionInterface::class);
    $condition->method('getField')->willReturn('status');
    $condition->expects($this->never())->method('getValue');
    $condition->expects($this->never())->method('getOperator');

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->never())->method('addCondition');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$condition])],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testEmptyNestedConditionGroupAddsNoConditions(): void {
    $this->setPassConditionsFields(['type' => 'type']);

    // A nested group with no children must not cause errors or add conditions.
    $nested_group = $this->createMock(ConditionGroupInterface::class);
    $nested_group->method('getConditions')->willReturn([]);

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->never())->method('addCondition');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$nested_group])],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testMatchingConditionInNestedGroupIsCopied(): void {
    $this->setPassConditionsFields(['type' => 'type']);

    $condition = $this->createMock(ConditionInterface::class);
    $condition->method('getField')->willReturn('type');
    $condition->method('getValue')->willReturn('article');
    $condition->method('getOperator')->willReturn('=');

    $nested_group = $this->createMock(ConditionGroupInterface::class);
    $nested_group->method('getConditions')->willReturn([$condition]);

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->once())
      ->method('addCondition')
      ->with('type', 'article', '=');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$nested_group])],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testDeeplyNestedConditionIsCopied(): void {
    $this->setPassConditionsFields(['type' => 'type']);

    $condition = $this->createMock(ConditionInterface::class);
    $condition->method('getField')->willReturn('type');
    $condition->method('getValue')->willReturn('page');
    $condition->method('getOperator')->willReturn('=');

    // Two levels of nesting: top-group → inner_group → condition.
    $inner_group = $this->createMock(ConditionGroupInterface::class);
    $inner_group->method('getConditions')->willReturn([$condition]);

    $outer_group = $this->createMock(ConditionGroupInterface::class);
    $outer_group->method('getConditions')->willReturn([$inner_group]);

    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->expects($this->once())
      ->method('addCondition')
      ->with('type', 'page', '=');

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$outer_group])],
    );
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testOnlyMatchingConditionsAmongMixedSetAreCopied(): void {
    $this->setPassConditionsFields(['type' => 'type', 'langcode' => 'langcode']);

    $type_condition = $this->createMock(ConditionInterface::class);
    $type_condition->method('getField')->willReturn('type');
    $type_condition->method('getValue')->willReturn('article');
    $type_condition->method('getOperator')->willReturn('=');

    // Status is not in pass_conditions_fields — must be skipped.
    $status_condition = $this->createMock(ConditionInterface::class);
    $status_condition->method('getField')->willReturn('status');
    $status_condition->expects($this->never())->method('getValue');

    $langcode_condition = $this->createMock(ConditionInterface::class);
    $langcode_condition->method('getField')->willReturn('langcode');
    $langcode_condition->method('getValue')->willReturn('en');
    $langcode_condition->method('getOperator')->willReturn('=');

    // A nested group with a non-configured field — its condition must be
    // skipped, but the group itself must still be recursed into.
    $nested_condition = $this->createMock(ConditionInterface::class);
    $nested_condition->method('getField')->willReturn('status');
    $nested_condition->expects($this->never())->method('getValue');

    $nested_group = $this->createMock(ConditionGroupInterface::class);
    $nested_group->method('getConditions')->willReturn([$nested_condition]);

    $added = [];
    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->method('addCondition')
      ->willReturnCallback(function (string $field, mixed $value, string $op) use (&$added): void {
        $added[] = [$field, $value, $op];
      });

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [
        $ai_query,
        $this->buildParentQuery([
          $type_condition,
          $status_condition,
          $nested_group,
          $langcode_condition,
        ]),
      ],
    );

    $this->assertCount(2, $added);
    $this->assertSame(['type', 'article', '='], $added[0]);
    $this->assertSame(['langcode', 'en', '='], $added[1]);
  }

  /**
   * @covers ::applyParentConditions
   */
  public function testMatchingConditionsFromMultipleNestedGroupsAreCopied(): void {
    $this->setPassConditionsFields(['type' => 'type', 'langcode' => 'langcode']);

    $type_condition = $this->createMock(ConditionInterface::class);
    $type_condition->method('getField')->willReturn('type');
    $type_condition->method('getValue')->willReturn('article');
    $type_condition->method('getOperator')->willReturn('=');

    $langcode_condition = $this->createMock(ConditionInterface::class);
    $langcode_condition->method('getField')->willReturn('langcode');
    $langcode_condition->method('getValue')->willReturn('en');
    $langcode_condition->method('getOperator')->willReturn('=');

    $group_a = $this->createMock(ConditionGroupInterface::class);
    $group_a->method('getConditions')->willReturn([$type_condition]);

    $group_b = $this->createMock(ConditionGroupInterface::class);
    $group_b->method('getConditions')->willReturn([$langcode_condition]);

    $added = [];
    $ai_query = $this->createMock(QueryInterface::class);
    $ai_query->method('addCondition')
      ->willReturnCallback(function (string $field, mixed $value, string $op) use (&$added): void {
        $added[] = [$field, $value, $op];
      });

    $this->applyParentConditionsMethod->invokeArgs(
      $this->processor,
      [$ai_query, $this->buildParentQuery([$group_a, $group_b])],
    );

    $this->assertCount(2, $added);
    $this->assertSame(['type', 'article', '='], $added[0]);
    $this->assertSame(['langcode', 'en', '='], $added[1]);
  }

}
