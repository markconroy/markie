<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_validations\Unit;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_validations\AiValidationsEntityContext;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests entity_context for validation chat calls.
 *
 * @group ai_validations
 */
#[Group('ai_validations')]
#[Group('3606133')]
#[CoversClass(AiValidationsEntityContext::class)]
final class AiValidationsEntityContextTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $module_root = dirname(__DIR__, 3);
    spl_autoload_register(static function (string $class) use ($module_root): void {
      $prefix = 'Drupal\\ai_validations\\';
      if (str_starts_with($class, $prefix)) {
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $module_root . '/src/' . $relative . '.php';
        if (file_exists($file)) {
          require_once $file;
        }
      }
    }, TRUE, TRUE);

    parent::setUp();
  }

  /**
   * Tests a saved entity includes type, bundle, and id.
   */
  public function testFromEntityIncludesTypeBundleAndId(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('article');
    $entity->method('id')->willReturn('8');

    $this->assertSame([
      'entity_type' => 'node',
      'bundle' => 'article',
      'id' => '8',
    ], AiValidationsEntityContext::fromEntity($entity));
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
    ], AiValidationsEntityContext::fromEntity($entity));
  }

  /**
   * Tests the validator root is used when the object has no host entity.
   */
  public function testFromValidatorContextFallsBackToRootEntity(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('node');
    $entity->method('bundle')->willReturn('page');
    $entity->method('id')->willReturn('3');

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->method('getRoot')->willReturn($entity);
    $context->method('getObject')->willReturn(NULL);

    $this->assertSame([
      'entity_type' => 'node',
      'bundle' => 'page',
      'id' => '3',
    ], AiValidationsEntityContext::fromValidatorContext($context));
  }

  /**
   * Tests a field item object unwraps its host entity.
   */
  public function testFromValidatorContextUnwrapsFieldItem(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $entity->method('getEntityTypeId')->willReturn('user');
    $entity->method('bundle')->willReturn('user');
    $entity->method('id')->willReturn('1');

    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getEntity')->willReturn($entity);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->method('getRoot')->willReturn(NULL);
    $context->method('getObject')->willReturn($item);

    $this->assertSame([
      'entity_type' => 'user',
      'bundle' => 'user',
      'id' => '1',
    ], AiValidationsEntityContext::fromValidatorContext($context));
  }

  /**
   * Tests the field host entity wins over a different validation root.
   *
   * A nested entity can be validated inside a parent entity's context.
   * The field's own host entity must be used, not the parent.
   */
  public function testFromValidatorContextPrefersObjectOverRoot(): void {
    $parent = $this->createMock(ContentEntityInterface::class);
    $parent->method('getEntityTypeId')->willReturn('node');
    $parent->method('bundle')->willReturn('page');
    $parent->method('id')->willReturn('3');

    $host = $this->createMock(ContentEntityInterface::class);
    $host->method('getEntityTypeId')->willReturn('paragraph');
    $host->method('bundle')->willReturn('text');
    $host->method('id')->willReturn('7');

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('getEntity')->willReturn($host);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->method('getRoot')->willReturn($parent);
    $context->method('getObject')->willReturn($items);

    $this->assertSame([
      'entity_type' => 'paragraph',
      'bundle' => 'text',
      'id' => '7',
    ], AiValidationsEntityContext::fromValidatorContext($context));
  }

  /**
   * Tests missing host values leave entity_context unset.
   */
  public function testFromValidatorContextReturnsNullWithoutEntity(): void {
    $context = $this->createMock(ExecutionContextInterface::class);
    $context->method('getRoot')->willReturn(NULL);
    $context->method('getObject')->willReturn(new \stdClass());

    $this->assertNull(
      AiValidationsEntityContext::fromValidatorContext($context),
    );
  }

  /**
   * Tests copying onto ChatInput when AI stores request metadata.
   */
  public function testApplyToInputCopiesOntoChatInput(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Hello'),
    ]);
    if (!AiValidationsEntityContext::inputSupportsRequestMetadata($input)) {
      $this->markTestSkipped(
        'This AI version does not store request metadata on ChatInput.',
      );
    }

    $entity_context = [
      'entity_type' => 'node',
      'bundle' => 'page',
      'id' => '',
    ];
    AiValidationsEntityContext::applyToInput($input, $entity_context);
    $this->assertSame(
      $entity_context,
      $input->getRequestMetadataValue(AiValidationsEntityContext::KEY),
    );
  }

}
