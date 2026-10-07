<?php

declare(strict_types=1);

namespace Drupal\Tests\field_widget_actions\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormValidatorInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase;
use Drupal\Tests\UnitTestCase;

/**
 * Unit tests for the pure logic in FieldWidgetRefinableFormActionBase.
 *
 * @group field_widget_actions
 *
 * @coversDefaultClass \Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase
 */
#[Group('field_widget_actions')]
class FieldWidgetRefinableFormActionBaseTest extends UnitTestCase {

  /**
   * Builds a concrete refinable plugin for testing the abstract base.
   *
   * @param array $configuration
   *   The plugin configuration.
   *
   * @return \Drupal\field_widget_actions\FieldWidgetRefinableFormActionBase
   *   A test double exposing the protected helpers under test.
   */
  protected function createPlugin(array $configuration = []): FieldWidgetRefinableFormActionBase {
    return new class(
      $configuration,
      'refinable_test',
      ['label' => 'Refinable', 'id' => 'refinable_test'],
      $this->createMock(MessengerInterface::class),
      $this->createMock(FormBuilderInterface::class),
      $this->createMock(PrivateTempStoreFactory::class),
      $this->createMock(UuidInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(EntityDisplayRepositoryInterface::class),
      $this->createMock(FormValidatorInterface::class),
    ) extends FieldWidgetRefinableFormActionBase {

      /**
       * {@inheritdoc}
       */
      public function generateContent(?ContentEntityInterface $entity, array $context_data): string {
        return 'generated';
      }

      /**
       * {@inheritdoc}
       */
      public function refineContent(string $content, string $refinement_prompt, ?ContentEntityInterface $entity, array $context_data): string {
        return $content;
      }

      /**
       * Exposes contentToString() for testing.
       */
      public function callContentToString(mixed $raw): string {
        return $this->contentToString($raw);
      }

      /**
       * Exposes targetIsFormattedText() for testing.
       */
      public function callTargetIsFormattedText(array $context_data): bool {
        return $this->targetIsFormattedText($context_data);
      }

      /**
       * Exposes getModalTitle() for testing.
       */
      public function callGetModalTitle(): string {
        return $this->getModalTitle();
      }

    };
  }

  /**
   * @covers ::contentToString
   * @dataProvider providerContentToString
   */
  #[DataProvider('providerContentToString')]
  public function testContentToString(mixed $raw, string $expected): void {
    $this->assertSame($expected, $this->createPlugin()->callContentToString($raw));
  }

  /**
   * Data provider for testContentToString().
   */
  public static function providerContentToString(): array {
    return [
      'text_format value shape' => [['value' => '<p>Hi</p>', 'format' => 'basic_html'], '<p>Hi</p>'],
      'array missing value key' => [['format' => 'basic_html'], ''],
      'plain string passthrough' => ['just text', 'just text'],
      'null becomes empty string' => [NULL, ''],
      'empty array becomes empty string' => [[], ''],
    ];
  }

  /**
   * @covers ::targetIsFormattedText
   * @dataProvider providerTargetIsFormattedText
   */
  #[DataProvider('providerTargetIsFormattedText')]
  public function testTargetIsFormattedText(array $context_data, bool $expected): void {
    $this->assertSame($expected, $this->createPlugin()->callTargetIsFormattedText($context_data));
  }

  /**
   * Data provider for testTargetIsFormattedText().
   */
  public static function providerTargetIsFormattedText(): array {
    return [
      'text_format element has #base_type' => [
        ['target_element' => ['#base_type' => 'text_format', '#type' => 'textarea']],
        TRUE,
      ],
      'plain textarea has no #base_type' => [
        ['target_element' => ['#type' => 'textarea']],
        FALSE,
      ],
      'empty #base_type is not formatted' => [
        ['target_element' => ['#base_type' => '']],
        FALSE,
      ],
      'missing target element' => [[], FALSE],
    ];
  }

  /**
   * @covers ::getModalTitle
   */
  public function testGetModalTitleUsesCustomTitleWhenRefinementEnabled(): void {
    $plugin = $this->createPlugin([
      'enable_refinement' => TRUE,
      'refinement_modal_title' => 'Polish your prose',
    ]);
    $this->assertSame('Polish your prose', $plugin->callGetModalTitle());
  }

  /**
   * @covers ::getModalTitle
   */
  public function testGetModalTitleFallsBackToLabelWhenTitleEmpty(): void {
    $plugin = $this->createPlugin([
      'enable_refinement' => TRUE,
      'refinement_modal_title' => '',
    ]);
    $this->assertSame('Refinable', $plugin->callGetModalTitle());
  }

  /**
   * @covers ::getModalTitle
   */
  public function testGetModalTitleFallsBackToLabelWhenRefinementDisabled(): void {
    $plugin = $this->createPlugin([
      'enable_refinement' => FALSE,
      'refinement_modal_title' => 'Ignored title',
    ]);
    $this->assertSame('Refinable', $plugin->callGetModalTitle());
  }

}
