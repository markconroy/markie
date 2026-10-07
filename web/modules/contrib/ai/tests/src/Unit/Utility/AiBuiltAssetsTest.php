<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Utility;

use Drupal\Tests\UnitTestCase;

/**
 * Tests that the AI module's built front-end UI assets are present.
 *
 * @group ai
 */
class AiBuiltAssetsTest extends UnitTestCase {

  /**
   * Data provider for testAssetsAvailable().
   */
  public static function providerAssets(): array {
    return [
      'mdxeditor' => [
        'ui/mdxeditor/dist/assets/main.js',
        'ui/mdxeditor',
      ],
      'json-schema-editor' => [
        'ui/json-schema-editor/dist/ai-json-schema.js',
        'ui/json-schema-editor',
      ],
    ];
  }

  /**
   * Asserts that each expected built UI asset exists on disk.
   *
   * @dataProvider providerAssets
   */
  public function testAssetsAvailable(string $relative_path, string $build_dir): void {
    $path = __DIR__ . '/../../../../' . $relative_path;
    $this->assertFileExists($path, sprintf(
      'Built UI asset "%s" is missing. Run `npm install` and `npm run build` in %s inside the AI module.',
      $relative_path,
      $build_dir
    ));
  }

}
