<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\PluginBaseClasses;

use Drupal\ai_automators\PluginBaseClasses\RuleBase;
use Drupal\ai_automators\Traits\RichTextImageDescriptionTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Tests\UnitTestCase;

/**
 * Tests image description behavior in RuleBase.
 *
 * @group ai_automators
 */
class RuleBaseImageDescriptionTest extends UnitTestCase {

  /**
   * Tests form controls are added for image description feature.
   */
  public function testImageDescriptionAdvancedFieldsAreAdded(): void {
    $rule = $this->createRule();

    $form = $rule->exposeAddImageDescriptionConfigurationForm([]);
    $this->assertArrayHasKey('automator_include_image_descriptions', $form);
    $this->assertArrayHasKey('automator_image_description_metadata_field', $form);
    $this->assertArrayHasKey('automator_include_external_images', $form);
    $this->assertArrayHasKey('automator_max_image_descriptions', $form);
    $this->assertArrayHasKey('automator_image_description_prompt', $form);
  }

  /**
   * Tests generateTokens augments context when feature is enabled.
   */
  public function testGenerateTokensIncludesImageDescriptions(): void {
    $rule = $this->createRule();
    $rule->generatedDescriptionText = '1. A product photo on a table.';

    $entity = $this->createEntityMock('<p>Hello world</p>', 'text_long');
    $fieldDefinition = $this->createFieldDefinitionMock(3);

    $tokens = $rule->generateTokens($entity, $fieldDefinition, [
      'base_field' => 'body',
      'include_image_descriptions' => TRUE,
    ], 0);

    $this->assertStringContainsString('Hello world', $tokens['context']);
    $this->assertStringContainsString('Image descriptions:', $tokens['context']);
    $this->assertSame('1. A product photo on a table.', $tokens['image_descriptions']);
    $this->assertSame(3, $tokens['max_amount']);
    $this->assertSame(1, $rule->generateImageDescriptionCalls);
  }

  /**
   * Tests generateTokens skips image descriptions when disabled.
   */
  public function testGenerateTokensSkipsImageDescriptionsWhenDisabled(): void {
    $rule = $this->createRule();
    $rule->generatedDescriptionText = '1. This should never be used.';

    $entity = $this->createEntityMock('<p>Hello world</p>', 'text_long');
    $fieldDefinition = $this->createFieldDefinitionMock(1);

    $tokens = $rule->generateTokens($entity, $fieldDefinition, [
      'base_field' => 'body',
      'include_image_descriptions' => FALSE,
    ], 0);

    $this->assertStringNotContainsString('Image descriptions:', $tokens['context']);
    $this->assertSame('', $tokens['image_descriptions']);
    $this->assertSame(0, $rule->generateImageDescriptionCalls);
  }

  /**
   * Tests metadata is persisted to configured field using helper.
   */
  public function testStoreMetadataDelegatesToHelper(): void {
    $rule = $this->createRule();
    $entity = $this->createMock(ContentEntityInterface::class);

    $rule->setImageDescriptionMetadata([
      ['description' => 'A skyline at dusk.'],
    ]);

    $rule->exposeStoreImageDescriptionsMetadata($entity, [
      'image_description_metadata_field' => 'field_moderation_image_metadata',
    ]);

    $this->assertNotNull($rule->helperSpy->lastCall);
    $this->assertSame('field_moderation_image_metadata', $rule->helperSpy->lastCall['field']);
    $this->assertSame('A skyline at dusk.', $rule->helperSpy->lastCall['metadata'][0]['description']);
  }

  /**
   * Creates a testable rule instance with mocked dependencies.
   */
  protected function createRule(): TestableRuleBase {
    $reflection = new \ReflectionClass(TestableRuleBase::class);
    /** @var \Drupal\Tests\ai_automators\Unit\PluginBaseClasses\TestableRuleBase $rule */
    $rule = $reflection->newInstanceWithoutConstructor();

    $rule->helperSpy = new RuleBaseRichTextImageHelperSpy();
    $rule->setStringTranslation(new RuleBaseTranslationStub());
    return $rule;
  }

  /**
   * Creates a mock entity with configured base field values.
   */
  protected function createEntityMock(string $rawValue, string $fieldType): ContentEntityInterface {
    $baseFieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $baseFieldDefinition->method('getType')->willReturn($fieldType);

    $fieldItemList = $this->createMock(FieldItemListInterface::class);
    $fieldItemList->method('getValue')->willReturn([
      ['value' => $rawValue],
    ]);

    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('hasField')->with('body')->willReturn(TRUE);
    $entity->method('getFieldDefinition')->with('body')->willReturn($baseFieldDefinition);
    $entity->method('get')->with('body')->willReturn($fieldItemList);

    return $entity;
  }

  /**
   * Creates field definition mock used by generateTokens target field.
   */
  protected function createFieldDefinitionMock(int $cardinality): FieldDefinitionInterface {
    $storage = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage->method('getCardinality')->willReturn($cardinality);

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getFieldStorageDefinition')->willReturn($storage);
    return $fieldDefinition;
  }

}

/**
 * Spy helper used to inspect metadata storage calls.
 */
class RuleBaseRichTextImageHelperSpy {

  /**
   * Last storeMetadata call payload.
   *
   * @var array<string,mixed>|null
   */
  public ?array $lastCall = NULL;

  /**
   * Stores metadata call arguments.
   */
  public function storeMetadata(ContentEntityInterface $entity, string $field, array $metadata): void {
    $this->lastCall = [
      'entity' => $entity,
      'field' => $field,
      'metadata' => $metadata,
    ];
  }

}

/**
 * Translation stub for unit tests.
 */
class RuleBaseTranslationStub implements TranslationInterface {

  /**
   * {@inheritdoc}
   */
  public function translate($string, array $args = [], array $options = []) {
    return strtr((string) $string, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function translateString(TranslatableMarkup $translated_string) {
    return (string) $translated_string;
  }

  /**
   * {@inheritdoc}
   */
  public function formatPlural($count, $singular, $plural, array $args = [], array $options = []) {
    $template = $count == 1 ? $singular : $plural;
    $args['@count'] = $count;
    return strtr($template, $args);
  }

}

/**
 * Testable RuleBase implementation with exposed hooks.
 */
class TestableRuleBase extends RuleBase {

  use RichTextImageDescriptionTrait;

  /**
   * Predetermined generated image description text.
   *
   * @var string
   */
  public string $generatedDescriptionText = '';

  /**
   * Counter for image description generation calls.
   *
   * @var int
   */
  public int $generateImageDescriptionCalls = 0;

  /**
   * Spy helper.
   *
   * @var object
   */
  public object $helperSpy;

  /**
   * Exposes protected form helper for assertions.
   *
   * @param array<string,mixed> $form
   *   Form values.
   * @param array<string,mixed> $defaultValues
   *   The stored automator configuration values used as form defaults.
   *
   * @return array<string,mixed>
   *   Altered form.
   */
  public function exposeAddImageDescriptionConfigurationForm(array $form, array $defaultValues = []): array {
    return $this->addImageDescriptionConfigurationForm($form, $defaultValues);
  }

  /**
   * Exposes metadata store helper for assertions.
   */
  public function exposeStoreImageDescriptionsMetadata(ContentEntityInterface $entity, array $config): void {
    $this->storeImageDescriptionsMetadata($entity, $config);
  }

  /**
   * Sets internal collected metadata.
   */
  public function setImageDescriptionMetadata(array $metadata): void {
    $this->imageDescriptionMetadata = $metadata;
  }

  /**
   * Overrides description generation for deterministic unit testing.
   */
  protected function generateImageDescriptionsFromRawContext(string $rawContext, ContentEntityInterface $entity, array $automatorConfig, int $delta): string {
    $this->generateImageDescriptionCalls++;
    return $this->generatedDescriptionText;
  }

  /**
   * Returns spy helper instead of container service.
   */
  protected function getRichTextImageHelper() {
    return $this->helperSpy;
  }

  /**
   * {@inheritdoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $tokens = parent::generateTokens($entity, $fieldDefinition, $automatorConfig, $delta);
    return $this->appendImageDescriptionsToTokens($entity, $automatorConfig, (int) $delta, $tokens);
  }

  /**
   * {@inheritdoc}
   */
  public function generate(
    ContentEntityInterface $entity,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function storeValues(
    ContentEntityInterface $entity,
    array $values,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function verifyValue(
    ContentEntityInterface $entity,
    $value,
    FieldDefinitionInterface $fieldDefinition,
    array $automatorConfig,
  ) {
    return TRUE;
  }

}
