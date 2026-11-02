<?php

namespace Drupal\Tests\ai_translate\Kernel;

use Drupal\Core\Language\Language;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_translate\Controller\AiTranslateController;
use Drupal\ai_translate_test\Plugin\AiProvider\TranslateTestProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that AI translation does not corrupt HTML markup.
 *
 * @see https://www.drupal.org/project/ai_translate/issues/3585525
 *
 * @group ai_translate
 */
#[Group('ai_translate')]
class TranslationMarkupSanitizationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'language',
    'content_translation',
    'key',
    'ai',
    'ai_translate',
    'ai_translate_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['ai']);
    // Point the ai.settings default provider to our controllable test
    // provider, so no real API call is ever made.
    $this->config('ai.settings')
      ->set('default_providers.translate_text', [
        'provider_id' => 'ai_translate_test',
        'model_id' => 'test',
      ])
      ->save();
  }

  /**
   * Data provider for testTranslationDoesNotCorruptMarkup().
   *
   * Each case sets what the (mocked) AI provider returns, and the field
   * format under which it is processed, then asserts the value ends up
   * unchanged in the stored translation, unless explicitly noted otherwise.
   */
  public static function markupCorruptionProvider(): array {
    return [
      'href entity in formatted field is preserved' => [
        '<p><a href="https://example.com?a=1&amp;b=2">link</a></p>',
        'basic_html',
        '<p><a href="https://example.com?a=1&amp;b=2">link</a></p>',
      ],
      'literal escaped tag example in formatted field is preserved' => [
        '<p>Write &lt;strong&gt; for bold text</p>',
        'basic_html',
        '<p>Write &lt;strong&gt; for bold text</p>',
      ],
      'list and bold markup in formatted field is preserved' => [
        '<ul><li><strong>Bold</strong> item</li><li>Second &amp; last</li></ul>',
        'full_html',
        '<ul><li><strong>Bold</strong> item</li><li>Second &amp; last</li></ul>',
      ],
      'non breaking space entity in formatted field is preserved' => [
        '<p>10&nbsp;euros</p>',
        'basic_html',
        '<p>10&nbsp;euros</p>',
      ],
      'quotes in unformatted field are preserved' => [
        'He said "stop"',
        NULL,
        'He said "stop"',
      ],
      'inline backticks in formatted field are preserved' => [
        '`array_map()` must be used here',
        'basic_html',
        '`array_map()` must be used here',
      ],
      'closed html fence wrapping the whole response is stripped' => [
        "```html<p>Hello</p>```",
        'basic_html',
        '<p>Hello</p>',
      ],
      'closed fence wrapping an unformatted field is stripped' => [
        "```Hello```",
        NULL,
        'Hello',
      ],
      'inline backticks in unformatted field are preserved' => [
        '`array_map()` must be used here',
        NULL,
        '`array_map()` must be used here',
      ],
      'unclosed compact html fence is stripped' => [
        "```html<p>Hello</p>",
        'basic_html',
        '<p>Hello</p>',
      ],
      'fully escaped field is decoded (issue #3476038)' => [
        '&lt;p&gt;Hello&lt;/p&gt;',
        'basic_html',
        '<p>Hello</p>',
      ],
    ];
  }

  /**
   * Tests the full translation pipeline against markup corruption.
   *
   * @dataProvider markupCorruptionProvider
   */
  #[DataProvider('markupCorruptionProvider')]
  public function testTranslationDoesNotCorruptMarkup(string $ai_response, ?string $format, string $expected): void {
    // Pilot the AI provider response instead of relying on a real model.
    TranslateTestProvider::$response = $ai_response;

    $singleField = [
      '_columns' => ['value'],
      'value' => 'source text',
    ];
    if ($format !== NULL) {
      $singleField['format'] = $format;
    }

    $context = [];
    $controller = AiTranslateController::create($this->container);
    $controller->translateSingleField(
      $singleField,
      new Language(['id' => 'en']),
      new Language(['id' => 'it']),
      $context
    );

    $this->assertSame(
      $expected,
      $context['results']['processedTranslations'][0]['translated']['value']
    );
  }

  /**
   * Provides response wrappers independently of field formatting.
   */
  public static function responseWrapperProvider(): array {
    return [
      'fence with leading whitespace' => [" \n```html\n<p>Text</p>\n```", '<p>Text</p>'],
      'fence with trailing blank line' => ["```html\n<p>Text</p>\n```\n\n", '<p>Text</p>'],
      'unclosed fence' => ["```html\n<p>Text", '<p>Text'],
      'non html language tag' => ["```text\nTranslated text\n```", 'Translated text'],
      'symmetric quotes' => ['"Bonjour le monde"', 'Bonjour le monde'],
      'internal and trailing quotes' => ['He said "stop"', 'He said "stop"'],
      'inline backticks' => ['Use `code` here', 'Use `code` here'],
      'no wrapper' => ['Translated text', 'Translated text'],
      'empty response' => ['', ''],
      'closing fence on the text line' => ["```html\n<p>Text</p>```", '<p>Text</p>'],
      'two quoted words are preserved' => ['"Fish" and "Chips"', '"Fish" and "Chips"'],
    ];
  }

  /**
   * Tests normalization through the provider and public translator API.
   *
   * @dataProvider responseWrapperProvider
   */
  #[DataProvider('responseWrapperProvider')]
  public function testResponseWrappers(string $response, string $expected): void {
    TranslateTestProvider::$response = $response;
    $translator = $this->container->get('ai_translate.text_translator');
    $this->assertSame($expected, $translator->translateContent(
      'Source text',
      new Language(['id' => 'it']),
      new Language(['id' => 'en']),
    ));
  }

  /**
   * Tests a text_with_summary field, covering both of its columns.
   */
  public function testTextWithSummaryKeepsEveryColumn(): void {
    TranslateTestProvider::$response = '<p>Prix : 10&nbsp;euros &amp; plus</p>';

    $singleField = [
      '_columns' => ['value', 'summary'],
      'value' => 'source body',
      'summary' => 'source summary',
      'format' => 'full_html',
    ];

    $context = [];
    $controller = AiTranslateController::create($this->container);
    $controller->translateSingleField(
      $singleField,
      new Language(['id' => 'en']),
      new Language(['id' => 'it']),
      $context
    );

    $this->assertSame(
      '<p>Prix : 10&nbsp;euros &amp; plus</p>',
      $context['results']['processedTranslations'][0]['translated']['value']
    );
    $this->assertSame(
      '<p>Prix : 10&nbsp;euros &amp; plus</p>',
      $context['results']['processedTranslations'][0]['translated']['summary']
    );
  }

}
