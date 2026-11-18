<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_disclosure\DisclosureRecorderInterface;
use Drupal\ai_translate\TranslationDisclosure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the note that TranslationDisclosure builds, and when it builds it.
 *
 * @group ai_translate
 * @coversDefaultClass \Drupal\ai_translate\TranslationDisclosure
 */
#[Group('ai_translate')]
#[CoversClass(TranslationDisclosure::class)]
class TranslationDisclosureTest extends UnitTestCase {

  /**
   * Tests that the note names the language the text was translated from.
   */
  public function testNoteNamesSourceLanguage(): void {
    $this->assertSame('Translated from English.', $this->applyAndGetNote());
  }

  /**
   * Tests that the note stays correct when the source language is unknown.
   */
  public function testUnknownSourceLanguageIsOmitted(): void {
    $this->assertSame('Translated.', $this->applyAndGetNote(FALSE));
  }

  /**
   * Tests that nothing is written when the note cannot be built.
   *
   * The copied disclosure of a new translation is emptied before the grades
   * are applied. Building the note first keeps that destructive step out of
   * reach of a failure: the translation keeps the field it came with, rather
   * than losing it and gaining no grade in return.
   */
  public function testFieldIsUntouchedWhenTheNoteFails(): void {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->expects($this->never())->method('setValue');

    $langFrom = $this->createMock(LanguageInterface::class);
    $langFrom->method('getName')->willThrowException(new \RuntimeException('no language name'));

    $recorder = $this->createMock(DisclosureRecorderInterface::class);
    $recorder->expects($this->never())->method('apply');

    $logger = $this->createMock(LoggerChannelInterface::class);
    $logger->expects($this->once())->method('warning');
    $loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);
    $loggerFactory->method('get')->with('ai_translate')->willReturn($logger);

    $source = $this->createMock(ContentEntityInterface::class);
    $disclosure = new TranslationDisclosure(
      $this->configFactory(),
      $recorder,
      NULL,
      $loggerFactory,
    );
    $disclosure->apply($this->translation($field), $langFrom, $source);
  }

  /**
   * Runs apply() and returns the note handed to the recorder.
   *
   * @param bool $withLanguage
   *   FALSE to call apply() without a source language.
   *
   * @return string
   *   The note the recorder was given.
   */
  protected function applyAndGetNote(bool $withLanguage = TRUE): string {
    $translation = $this->translation();

    $langFrom = $this->createMock(LanguageInterface::class);
    $langFrom->method('getName')->willReturn('English');

    $note = NULL;
    $recorder = $this->createMock(DisclosureRecorderInterface::class);
    $recorder->expects($this->once())
      ->method('apply')
      ->with($translation, 'ai_translated', 'ai_translate', $this->anything(), FALSE)
      ->willReturnCallback(function ($entity, $grade, $source, $text) use (&$note): void {
        $note = $text;
      });

    (new TranslationDisclosure($this->configFactory(), $recorder))
      ->apply($translation, $withLanguage ? $langFrom : NULL);
    return $note;
  }

  /**
   * Builds a config factory that reports the feature as switched on.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  protected function configFactory(): ConfigFactoryInterface {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->with('ai_disclosure_apply')->willReturn(TRUE);
    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->with('ai_translate.settings')->willReturn($settings);
    return $configFactory;
  }

  /**
   * Builds a translation carrying one translatable AI Disclosure field.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface|null $field
   *   The field item list to hand back from get(), when the test inspects it.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The translation.
   */
  protected function translation(?FieldItemListInterface $field = NULL): ContentEntityInterface {
    $definition = $this->createMock(FieldDefinitionInterface::class);
    $definition->method('getType')->willReturn('ai_disclosure');
    $definition->method('isTranslatable')->willReturn(TRUE);

    $translation = $this->createMock(ContentEntityInterface::class);
    $translation->method('getFieldDefinitions')->willReturn(['field_ai_disclosure' => $definition]);
    if ($field !== NULL) {
      $translation->method('get')->with('field_ai_disclosure')->willReturn($field);
    }
    return $translation;
  }

}
