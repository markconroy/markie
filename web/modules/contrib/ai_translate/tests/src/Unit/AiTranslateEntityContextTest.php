<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_translate\AiTranslateEntityContext;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity_context sanitizing for chat-proxy translation.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[CoversClass(AiTranslateEntityContext::class)]
final class AiTranslateEntityContextTest extends UnitTestCase {

  /**
   * Tests a loaded entity becomes a translateContent() $context array.
   */
  public function testFromEntityIncludesTypeBundleAndId(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('id')->willReturn('8');

    $this->assertSame([
      'entity_context' => [
        'entity_type' => 'node',
        'bundle' => 'article',
        'id' => '8',
      ],
    ], AiTranslateEntityContext::toContext($entity));
  }

  /**
   * Tests unsaved entities keep an empty id.
   */
  public function testFromEntityAllowsEmptyId(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('page');
    $entity->method('id')->willReturn(NULL);

    $this->assertSame([
      'entity_type' => 'node',
      'bundle' => 'page',
      'id' => '',
    ], AiTranslateEntityContext::fromEntity($entity));
  }

  /**
   * Tests a malformed $context array is ignored.
   */
  public function testFromContextRejectsInvalidValues(): void {
    $this->assertNull(AiTranslateEntityContext::fromContext([]));
    $this->assertNull(AiTranslateEntityContext::fromContext([
      'entity_context' => 'node:8',
    ]));
    $this->assertNull(AiTranslateEntityContext::fromContext([
      'entity_context' => ['bundle' => 'article'],
    ]));
  }

  /**
   * Tests type aliases are normalized to entity_type.
   */
  public function testSanitizeAcceptsTypeAlias(): void {
    $context = [
      'entity_context' => [
        'type' => 'media',
        'bundle' => 'image',
        'id' => 12,
      ],
    ];
    $this->assertSame([
      'entity_type' => 'media',
      'bundle' => 'image',
      'id' => '12',
    ], AiTranslateEntityContext::fromContext($context));
  }

  /**
   * Tests copying onto ChatInput when AI stores request metadata.
   */
  public function testApplyFromContextCopiesOntoChatInput(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Hello'),
    ]);
    if (!AiTranslateEntityContext::inputSupportsRequestMetadata($input)) {
      $this->markTestSkipped(
        'This AI version does not store request metadata on ChatInput.',
      );
    }

    $context = [
      'entity_context' => [
        'type' => 'media',
        'bundle' => 'image',
        'id' => 12,
      ],
    ];
    $sanitized = AiTranslateEntityContext::fromContext($context);
    AiTranslateEntityContext::applyFromContext($input, $context);
    $this->assertSame(
      $sanitized,
      $input->getRequestMetadataValue('entity_context'),
    );
  }

}
