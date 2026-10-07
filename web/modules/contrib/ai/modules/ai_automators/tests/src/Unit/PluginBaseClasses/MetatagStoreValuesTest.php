<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit\PluginBaseClasses;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ai_automators\PluginBaseClasses\Metatag;
use Drupal\Tests\UnitTestCase;

/**
 * Tests how generated metatag values are stored.
 *
 * The prompt asks for one record holding every configured tag, but models
 * routinely answer with one record per tag. Only a single entry was
 * read out of the generated values, so a run that produced thirteen tags
 * stored one and dropped the rest. The generated set is also
 * restricted to the tags that carry a subprompt.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\PluginBaseClasses\Metatag
 */
class MetatagStoreValuesTest extends UnitTestCase {

  /**
   * One record per tag is merged into a single tag set.
   */
  public function testOneRecordPerTagIsMerged(): void {
    $stored = $this->store([
      ['title' => '10 Best Drupal Modules for 2024'],
      ['description' => 'Explore the ten best modules.'],
      ['abstract' => 'Tired of boring Drupal sites?'],
    ]);

    $this->assertSame([
      'title' => '10 Best Drupal Modules for 2024',
      'description' => 'Explore the ten best modules.',
      'abstract' => 'Tired of boring Drupal sites?',
    ], Json::decode($stored));
  }

  /**
   * A single record holding every tag still works.
   */
  public function testSingleRecordIsStored(): void {
    $stored = $this->store([
      ['title' => 'A title', 'abstract' => 'An abstract'],
    ]);

    $this->assertSame(['title' => 'A title', 'abstract' => 'An abstract'], Json::decode($stored));
  }

  /**
   * A tag the model repeated keeps its first value.
   */
  public function testRepeatedTagKeepsTheFirstValue(): void {
    $stored = $this->store([
      ['article_tag' => 'Drupal'],
      ['article_tag' => 'Web Development'],
    ]);

    $this->assertSame(['article_tag' => 'Drupal'], Json::decode($stored));
  }

  /**
   * Empty and non-record entries are skipped rather than stored.
   */
  public function testUnusableEntriesAreSkipped(): void {
    $stored = $this->store([
      'not a record',
      ['abstract' => ''],
      ['abstract' => 'A real abstract'],
    ]);

    $this->assertSame(['abstract' => 'A real abstract'], Json::decode($stored));
  }

  /**
   * Nothing usable means nothing is written to the field.
   */
  public function testNothingUsableWritesNothing(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->never())->method('set');

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getName')->willReturn('field_meta');

    $this->rule()->storeValues($entity, ['nope', ['abstract' => '']], $fieldDefinition, []);
  }

  /**
   * Tags nobody wrote a subprompt for are not stored.
   */
  public function testUnconfiguredTagsAreDropped(): void {
    $stored = $this->store([
      ['title' => 'A title the site builder never asked for'],
      ['description' => 'Nor this description'],
      ['abstract' => 'But this abstract was requested'],
    ], ['llm_tag_value_abstract' => 'Write a witty abstract.']);

    $this->assertSame(['abstract' => 'But this abstract was requested'], Json::decode($stored));
  }

  /**
   * An empty subprompt does not count as a configured tag.
   */
  public function testEmptySubpromptDoesNotConfigureTag(): void {
    $stored = $this->store([
      ['title' => 'Generated title'],
      ['abstract' => 'Generated abstract'],
    ], [
      'llm_tag_value_title' => '',
      'llm_tag_value_abstract' => 'Write a witty abstract.',
    ]);

    $this->assertSame(['abstract' => 'Generated abstract'], Json::decode($stored));
  }

  /**
   * Nothing is written when every generated tag was unconfigured.
   */
  public function testAllTagsUnconfiguredWritesNothing(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->never())->method('set');

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getName')->willReturn('field_meta');

    $this->rule()->storeValues(
      $entity,
      [['title' => 'Generated title']],
      $fieldDefinition,
      ['llm_tag_value_abstract' => 'Write a witty abstract.'],
    );
  }

  /**
   * Runs storeValues() and returns the JSON blob written to the field.
   *
   * @param array $values
   *   The generated values.
   * @param array $automatorConfig
   *   The automator configuration, carrying any llm_tag_value_* subprompts.
   *
   * @return string
   *   The stored JSON.
   */
  private function store(array $values, array $automatorConfig = []): string {
    $stored = '';
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->expects($this->once())
      ->method('set')
      ->willReturnCallback(function (string $field, $value) use (&$stored) {
        $stored = $value;
        return NULL;
      });

    $fieldDefinition = $this->createMock(FieldDefinitionInterface::class);
    $fieldDefinition->method('getName')->willReturn('field_meta');

    $this->rule()->storeValues($entity, $values, $fieldDefinition, $automatorConfig);
    return $stored;
  }

  /**
   * Builds the rule without invoking its constructor.
   *
   * @return \Drupal\ai_automators\PluginBaseClasses\Metatag
   *   The rule instance.
   */
  private function rule(): Metatag {
    return (new \ReflectionClass(Metatag::class))->newInstanceWithoutConstructor();
  }

}
