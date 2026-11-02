<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_chatbot\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\ai_chatbot\Plugin\Block\DeepChatFormBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the DeepChat width and height cannot inject markup.
 *
 * @group ai_chatbot
 */
#[RunTestsInSeparateProcesses]
class DeepChatBlockStylingTest extends KernelTestBase {

  /**
   * A width that closes the style attribute and injects an element.
   */
  protected const PAYLOAD = "300px'></deep-chat><img src=x onerror=\"fetch('/xss-fired')\">";

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'token',
    'key',
    'ai',
    'ai_assistant_api',
    'ai_chatbot',
    'block',
  ];

  /**
   * Tests that both DeepChat templates escape the element attributes.
   */
  #[DataProvider('providerThemeHooks')]
  public function testTemplatesEscapeDeepChatSettings(string $theme_hook): void {
    $build = [
      '#theme' => $theme_hook,
      '#settings' => ['width' => '400px', 'height' => '500px'],
      '#deepchat_settings' => ['style' => 'width: ' . self::PAYLOAD . ';'],
    ];
    $output = (string) $this->container->get('renderer')->renderInIsolation($build);

    $this->assertStringNotContainsString('<img', $output);
    $this->assertStringContainsString('&lt;img src=x onerror=&quot;fetch(&#039;/xss-fired&#039;)&quot;&gt;', $output);
  }

  /**
   * Data provider for testTemplatesEscapeDeepChatSettings().
   */
  public static function providerThemeHooks(): array {
    return [
      'floating' => ['ai_deepchat'],
      'toolbar' => ['ai_deepchat__toolbar'],
    ];
  }

  /**
   * Tests that an invalid stored width or height never reaches the style.
   *
   * Config can be imported without going through the block form, so the
   * plugin has to reject it at render time as well.
   */
  public function testInvalidStoredDimensionsFallBackToDefaults(): void {
    $block = $this->createBlockPlugin([
      'width' => self::PAYLOAD,
      'height' => '500px; background: url(https://example.com/x)',
    ]);

    $parameters = $block->getDeepChatParameters('module:ai_chatbot:bard.yml');

    $this->assertStringNotContainsString('xss-fired', $parameters['style']);
    $this->assertStringNotContainsString('example.com', $parameters['style']);
    $this->assertStringEndsWith('height: 500px; width: 500px;', $parameters['style']);
  }

  /**
   * Tests that the block form rejects anything but a CSS length.
   */
  public function testBlockValidateRejectsInvalidDimensions(): void {
    $block = $this->createBlockPlugin();

    $form_state = $this->buildStylingFormState('bottom-right', self::PAYLOAD, '500px');
    $block->blockValidate([], $form_state);
    $this->assertArrayHasKey('styling][width', $form_state->getErrors());
    $this->assertArrayNotHasKey('styling][height', $form_state->getErrors());

    $form_state = $this->buildStylingFormState('bottom-left', '30rem', '80vh');
    $block->blockValidate([], $form_state);
    $this->assertSame([], $form_state->getErrors());

    // The toolbar placement overrides both values on submit.
    $form_state = $this->buildStylingFormState('toolbar', self::PAYLOAD, self::PAYLOAD);
    $block->blockValidate([], $form_state);
    $this->assertSame([], $form_state->getErrors());
  }

  /**
   * Tests the CSS length check.
   */
  #[DataProvider('providerCssLengths')]
  public function testIsValidCssLength(string $value, bool $expected): void {
    $this->assertSame($expected, DeepChatFormBlock::isValidCssLength($value));
  }

  /**
   * Data provider for testIsValidCssLength().
   */
  public static function providerCssLengths(): array {
    return [
      ['500px', TRUE],
      ['100%', TRUE],
      ['12.5rem', TRUE],
      ['80vh', TRUE],
      ['auto', TRUE],
      ['', FALSE],
      ['500', FALSE],
      ['500px;', FALSE],
      ['500px; color: red', FALSE],
      ["500px'", FALSE],
      ["500px\n", FALSE],
      ['calc(100% - 1px)', FALSE],
      [self::PAYLOAD, FALSE],
    ];
  }

  /**
   * Creates a DeepChat block plugin.
   *
   * @param array $configuration
   *   Configuration to merge on top of the defaults.
   *
   * @return \Drupal\ai_chatbot\Plugin\Block\DeepChatFormBlock
   *   The block plugin.
   */
  protected function createBlockPlugin(array $configuration = []): DeepChatFormBlock {
    $block = $this->container->get('plugin.manager.block')->createInstance('ai_deepchat_block', $configuration + [
      'placement' => 'bottom-right',
    ]);
    $this->assertInstanceOf(DeepChatFormBlock::class, $block);
    return $block;
  }

  /**
   * Builds a form state holding the styling values of the block form.
   */
  protected function buildStylingFormState(string $placement, string $width, string $height): FormState {
    return (new FormState())->setValue('styling', [
      'placement' => $placement,
      'width' => $width,
      'height' => $height,
    ]);
  }

}
