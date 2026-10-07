<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\Plugin\AiAutomatorType;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\Plugin\AiAutomatorType\LlmModerationState;
use Drupal\Tests\UnitTestCase;

/**
 * Tests how the moderation state rule stores a generated state.
 *
 * Issue #3586715: clicking "Automator Moderation State" left the state
 * unchanged with nothing in the logs. The generated {state: "..."} record was
 * being taken apart before it reached storeValues(), which then looked for a
 * 'state' key on a bare string and quietly stored nothing.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\Plugin\AiAutomatorType\LlmModerationState
 */
class LlmModerationStateStoreValuesTest extends UnitTestCase {

  /**
   * A generated record whose state is enabled for lookup is stored.
   */
  public function testEnabledStateIsStored(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('set')
      ->with('moderation_state', 'published');

    $this->store($entity, [['state' => 'published']], [
      'trigger_lookup' => ['draft' => 'draft', 'published' => 'published', 'archived' => 0],
    ]);
  }

  /**
   * A state the site builder did not enable for lookup is not stored.
   */
  public function testStateOutsideTheLookupListIsIgnored(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->never())->method('set');

    $this->store($entity, [['state' => 'archived']], [
      'trigger_lookup' => ['draft' => 'draft', 'published' => 'published', 'archived' => 0],
    ]);
  }

  /**
   * A model answering with the state name only is still accepted.
   */
  public function testBareStateStringIsStored(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('set')
      ->with('moderation_state', 'draft');

    $this->store($entity, ['draft'], [
      'trigger_lookup' => ['draft' => 'draft', 'published' => 'published'],
    ]);
  }

  /**
   * An automator config without the optional keys does not raise a warning.
   */
  public function testMissingOptionalConfigKeysAreTolerated(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('set')
      ->with('moderation_state', 'published');

    // No 'store_explanation' and no 'use_simple_model' key at all.
    $this->store($entity, [['state' => 'published']], [
      'trigger_lookup' => ['published' => 'published'],
    ]);
  }

  /**
   * The reasoning is written to the configured explanation field.
   */
  public function testReasoningIsStoredInTheExplanationField(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->exactly(2))
      ->method('set')
      ->willReturnCallback(function (string $field, $value) {
        static $calls = [];
        $calls[$field] = $value;
        $this->assertContains($field, ['field_explanation', 'moderation_state']);
        return NULL;
      });

    $this->store($entity, [['state' => 'draft', 'reasoning' => 'Contains swear words.']], [
      'trigger_lookup' => ['draft' => 'draft'],
      'store_explanation' => 'field_explanation',
    ]);
  }

  /**
   * Verification accepts a record and rejects everything else.
   *
   * @dataProvider providerVerifyValues
   */
  public function testVerifyValue($value, bool $expected): void {
    $rule = $this->rule();

    $this->assertSame($expected, $rule->verifyValue(
      $this->createMock(ContentEntityInterface::class),
      $value,
      $this->createMock(FieldDefinitionInterface::class),
      [],
    ));
  }

  /**
   * Values the rule may be handed.
   *
   * @return array
   *   Test cases of value and expected result.
   */
  public static function providerVerifyValues(): array {
    return [
      'record' => [['state' => 'published'], TRUE],
      'record without a state' => [['reasoning' => 'because'], FALSE],
      'record with an empty state' => [['state' => ''], FALSE],
      'simple model string' => ['published', TRUE],
      'empty string' => ['', FALSE],
      'null' => [NULL, FALSE],
    ];
  }

  /**
   * Calls storeValues() against a moderation_state field definition.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity mock carrying the expectations.
   * @param array $values
   *   The generated values.
   * @param array $automatorConfig
   *   The automator configuration.
   */
  private function store(ContentEntityInterface $entity, array $values, array $automatorConfig): void {
    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getName')->willReturn('moderation_state');

    $this->rule()->storeValues($entity, $values, $fieldDefinition, $automatorConfig);
  }

  /**
   * Builds the rule without invoking its constructor.
   *
   * AiProviderPluginManager and AiProviderFormHelper are final and cannot be
   * mocked; the methods under test do not touch them.
   *
   * @return \Drupal\ai_automators\Plugin\AiAutomatorType\LlmModerationState
   *   The rule instance.
   */
  private function rule(): LlmModerationState {
    return (new \ReflectionClass(LlmModerationState::class))->newInstanceWithoutConstructor();
  }

}
