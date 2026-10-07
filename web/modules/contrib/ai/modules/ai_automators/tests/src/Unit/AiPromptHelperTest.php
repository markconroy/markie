<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_automators\Unit;

use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\Core\Utility\Token;
use Drupal\ai_automators\AiPromptHelper;
use Drupal\Tests\UnitTestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * Tests AiPromptHelper::renderPrompt() escaping behavior.
 *
 * @group ai_automators
 * @coversDefaultClass \Drupal\ai_automators\AiPromptHelper
 */
class AiPromptHelperTest extends UnitTestCase {

  /**
   * Returns a real Twig environment with HTML autoescaping on (Drupal default).
   */
  protected function realTwigEnvironment(): TwigEnvironment {
    $inner = new Environment(new ArrayLoader([]), ['autoescape' => 'html']);
    // TwigEnvironment wraps a real Twig Environment; mock only the methods
    // we need, delegating createTemplate() to the inner environment.
    $twig = $this->createMock(TwigEnvironment::class);
    $twig->method('createTemplate')
      ->willReturnCallback(fn(string $t) => $inner->createTemplate($t));
    return $twig;
  }

  /**
   * Returns a helper instance backed by the real Twig environment.
   */
  protected function helper(): AiPromptHelper {
    $user = $this->createMock(AccountProxy::class);
    $token = $this->createMock(Token::class);
    return new AiPromptHelper($this->realTwigEnvironment(), $user, $token);
  }

  /**
   * @covers ::renderPrompt
   */
  public function testAmpersandInTokenValueIsNotEscaped(): void {
    $helper = $this->helper();
    $result = $helper->renderPrompt(
      'Choose from: {{ options }}',
      ['options' => 'Latin America & Caribbean, Europe'],
    );
    $this->assertSame('Choose from: Latin America & Caribbean, Europe', $result);
  }

  /**
   * @covers ::renderPrompt
   */
  public function testAllHtmlSpecialCharsInTokenValueAreNotEscaped(): void {
    $helper = $this->helper();
    $result = $helper->renderPrompt(
      'Value: {{ val }}',
      ['val' => 'a & b < c > d " e \' f'],
    );
    $this->assertSame("Value: a & b < c > d \" e ' f", $result);
  }

  /**
   * @covers ::renderPrompt
   */
  public function testHtmlEntitiesInPromptTemplateAreDecoded(): void {
    // Prompt text saved via a form widget may arrive HTML-encoded.
    $helper = $this->helper();
    $result = $helper->renderPrompt(
      'Use &quot;formal&quot; tone &amp; keep it short.',
      [],
    );
    $this->assertSame('Use "formal" tone & keep it short.', $result);
  }

  /**
   * @covers ::renderPrompt
   */
  public function testPlainPromptWithNoSpecialCharsIsUnchanged(): void {
    $helper = $this->helper();
    $result = $helper->renderPrompt(
      'Summarize this text: {{ body }}',
      ['body' => 'The quick brown fox.'],
    );
    $this->assertSame('Summarize this text: The quick brown fox.', $result);
  }

}
