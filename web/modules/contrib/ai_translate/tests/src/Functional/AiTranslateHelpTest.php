<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Functional;

use Drupal\ai_translate\Hook\HelpHooks;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AI Translate help page.
 *
 * @group ai_translate
 */
#[CoversClass(HelpHooks::class)]
#[Group('ai_translate')]
#[RunTestsInSeparateProcesses]
class AiTranslateHelpTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ai_translate', 'help'];

  /**
   * Tests that the module help text is shown on its help page.
   */
  public function testHelpPage(): void {
    $this->drupalLogin($this->drupalCreateUser(['access help pages']));
    $this->drupalGet('admin/help/ai_translate');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('It will add a Translate link to translate an entire piece of content');
    $this->assertSession()->linkByHrefExists('https://www.drupal.org/project/ai_translate');
  }

}
