<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai_translate\AiTranslateLanguageContext;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests language_context sanitizing for chat-proxy translation.
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
#[CoversClass(AiTranslateLanguageContext::class)]
final class AiTranslateLanguageContextTest extends UnitTestCase {

  /**
   * Tests target-only context when source is unknown.
   */
  public function testFromLanguagesOmitsEmptySource(): void {
    $this->assertSame([
      'target' => 'de',
    ], AiTranslateLanguageContext::fromLanguages(NULL, 'de'));
    $this->assertSame([
      'target' => 'fr',
    ], AiTranslateLanguageContext::fromLanguages('', 'fr'));
  }

  /**
   * Tests source and target are both kept when valid.
   */
  public function testFromLanguagesKeepsSourceAndTarget(): void {
    $this->assertSame([
      'target' => 'fr',
      'source' => 'en',
    ], AiTranslateLanguageContext::fromLanguages('en', 'fr'));
  }

  /**
   * Tests hyphenated langcodes are allowed.
   */
  public function testSanitizeAllowsRegionalLangcodes(): void {
    $this->assertSame([
      'target' => 'pt-br',
      'source' => 'zh-hans',
    ], AiTranslateLanguageContext::sanitize([
      'target' => 'pt-br',
      'source' => 'zh-hans',
      'extra' => 'drop-me',
    ]));
  }

  /**
   * Tests langcodes created outside the language add form are kept.
   *
   * Config import and the API accept underscores and langcodes longer than
   * the 12 characters the form allows.
   */
  public function testSanitizeAllowsLangcodesFromConfigOrApi(): void {
    $long = 'x-' . str_repeat('a', 40);
    $this->assertSame([
      'target' => 'zh_hans',
      'source' => $long,
    ], AiTranslateLanguageContext::sanitize([
      'target' => 'zh_hans',
      'source' => $long,
    ]));
  }

  /**
   * Tests a langcode longer than a language config name allows is rejected.
   */
  public function testSanitizeRejectsLangcodeLongerThanConfigName(): void {
    $this->assertNull(AiTranslateLanguageContext::sanitize([
      'target' => str_repeat('a', 235),
    ]));
    $this->assertSame([
      'target' => str_repeat('a', 234),
    ], AiTranslateLanguageContext::sanitize([
      'target' => str_repeat('a', 234),
    ]));
  }

  /**
   * Tests invalid target is rejected.
   */
  public function testSanitizeRejectsInvalidTarget(): void {
    $this->assertNull(AiTranslateLanguageContext::sanitize([]));
    $this->assertNull(AiTranslateLanguageContext::sanitize([
      'target' => '',
    ]));
    $this->assertNull(AiTranslateLanguageContext::sanitize([
      'target' => 'en/fr',
    ]));
    $this->assertNull(AiTranslateLanguageContext::sanitize([
      'source' => 'en',
    ]));
  }

  /**
   * Tests invalid source is dropped without dropping target.
   */
  public function testSanitizeDropsInvalidSource(): void {
    $this->assertSame([
      'target' => 'de',
    ], AiTranslateLanguageContext::sanitize([
      'target' => 'de',
      'source' => 'not a code',
    ]));
  }

  /**
   * Tests copying onto ChatInput when AI stores request metadata.
   */
  public function testApplyToInputCopiesOntoChatInput(): void {
    $input = new ChatInput([
      new ChatMessage('user', 'Hello'),
    ]);
    if (!AiTranslateLanguageContext::inputSupportsRequestMetadata($input)) {
      $this->markTestSkipped(
        'This AI version does not store request metadata on ChatInput.',
      );
    }

    $sanitized = AiTranslateLanguageContext::fromLanguages('en', 'es');
    $this->assertNotNull($sanitized);
    AiTranslateLanguageContext::applyToInput($input, $sanitized);
    $this->assertSame(
      $sanitized,
      $input->getRequestMetadataValue('language_context'),
    );
  }

}
