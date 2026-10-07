<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_ckeditor\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Tests that the AI CKEditor built JS assets are present.
 *
 * @group ai_ckeditor
 */
class AiCKEditorAssetsTest extends UnitTestCase {

  /**
   * Data provider for testAssetsAvailable().
   */
  public static function providerAssets(): array {
    return [
      'aickeditor.js' => ['aickeditor.js'],
      'ai_balloon_menu.js' => ['ai_balloon_menu.js'],
    ];
  }

  /**
   * Asserts that each expected built asset exists on disk.
   *
   * @dataProvider providerAssets
   */
  public function testAssetsAvailable(string $filename): void {
    $path = __DIR__ . '/../../../js/build/' . $filename;
    $this->assertFileExists($path, sprintf(
      'Built CKEditor asset "%s" is missing. Run `npm ci` and `npm run build` in modules/ai_ckeditor.',
      $filename
    ));
  }

}
