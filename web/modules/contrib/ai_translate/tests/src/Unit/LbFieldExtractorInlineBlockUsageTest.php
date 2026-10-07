<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\ai_translate\Plugin\FieldTextExtractor\LbFieldExtractor;
use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;
use Drupal\Component\Uuid\UuidInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests inline block usage registration in asymmetric LB AI translation.
 */
#[Group('ai_translate')]
final class LbFieldExtractorInlineBlockUsageTest extends UnitTestCase {

  /**
   * Tests cloned asymmetric Layout Builder blocks register usage.
   *
   * @throws \PHPUnit\Framework\MockObject\Exception
   */
  public function testAsymmetricTranslationRegistersInlineBlockUsage(): void {
    $component = new SectionComponent(
      '11111111-1111-4111-8111-111111111111',
      'content',
      [
        'id' => 'inline_block:test_inline',
        'label' => 'Inline block',
        'label_display' => FALSE,
        'provider' => 'layout_builder',
        'view_mode' => 'full',
        'block_id' => '1',
        'block_revision_id' => '2',
        'block_uuid' => 'source-uuid',
        'block_serialized' => NULL,
        'context_mapping' => [],
      ],
    );
    $section = new Section('layout_onecol', [], [$component->getUuid() => $component]);

    $source_field = $this->createMock(FieldItemListInterface::class);
    $source_field
      ->method('getValue')
      ->willReturn([
        ['section' => $section],
      ]);

    $translated_field = $this->createMock(FieldItemListInterface::class);
    $translated_field_set_value_calls = [];
    $translated_field
      ->method('setValue')
      ->willReturnCallback(static function (mixed $value) use (&$translated_field_set_value_calls): void {
        $translated_field_set_value_calls[] = $value;
      });

    $source_entity = $this->createMock(ContentEntityInterface::class);
    $translated_entity = $this->createMock(ContentEntityInterface::class);

    $source_entity
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn($source_field);

    $translated_entity
      ->method('getUntranslated')
      ->willReturn($source_entity);
    $translated_entity
      ->method('get')
      ->with('layout_builder__layout')
      ->willReturn($translated_field);
    $translated_entity
      ->expects($this->once())
      ->method('save');

    $source_block = $this->createMock(BlockContentInterface::class);
    $translated_block = $this->createMock(BlockContentInterface::class);

    $source_block
      ->method('createDuplicate')
      ->willReturn($translated_block);

    $translated_block
      ->method('set')
      ->willReturnSelf();
    $translated_block
      ->method('id')
      ->willReturn(99);
    $translated_block
      ->method('getRevisionId')
      ->willReturn(123);
    $translated_block
      ->method('uuid')
      ->willReturn('generated-uuid');
    $translated_block
      ->expects($this->once())
      ->method('save');

    $block_storage = $this->createMock(RevisionableStorageInterface::class);
    $block_storage
      ->expects($this->once())
      ->method('loadByProperties')
      ->with(['uuid' => 'source-uuid'])
      ->willReturn([$source_block]);

    $inline_block_usage = $this->createMock(InlineBlockUsageInterface::class);
    $inline_block_usage
      ->expects($this->once())
      ->method('addUsage')
      ->with(99, $translated_entity);

    $uuid = $this->createMock(UuidInterface::class);
    $uuid->method('generate')->willReturn('generated-uuid');

    $extractor = new LbFieldExtractor([], 'layout_builder', []);
    $this->setProtectedProperty($extractor, 'blockStorage', $block_storage);
    $this->setProtectedProperty($extractor, 'inlineBlockUsage', $inline_block_usage);
    $this->setProtectedProperty($extractor, 'uuid', $uuid);
    $this->setProtectedProperty($extractor, 'logger', $this->createMock(LoggerInterface::class));

    $method = new \ReflectionMethod(LbFieldExtractor::class, 'asymmetricLayoutBuilderBlockTranslation');
    $method->invoke($extractor, $translated_entity, 'layout_builder__layout', 'fr', []);

    self::assertCount(1, $translated_field_set_value_calls);
    $duplicated_sections = $translated_field_set_value_calls[0];
    self::assertCount(1, $duplicated_sections);

    $components = $duplicated_sections[0]->getComponents();
    self::assertCount(1, $components);
    $duplicated_component = reset($components);
    $configuration = $duplicated_component->get('configuration');

    self::assertSame('99', (string) $configuration['block_id']);
    self::assertSame('123', (string) $configuration['block_revision_id']);
    self::assertSame('generated-uuid', $configuration['block_uuid']);
  }

  /**
   * Sets a protected property value on the extractor.
   */
  private function setProtectedProperty(LbFieldExtractor $extractor, string $property_name, mixed $value): void {
    $property = new \ReflectionProperty(LbFieldExtractor::class, $property_name);
    $property->setValue($extractor, $value);
  }

}
