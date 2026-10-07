<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @covers \Drupal\Core\Extension\ExtensionDiscovery
 * @group ai
 */
class DeprecatedSubmoduleDiscoveryTest extends UnitTestCase {

  /**
   * The machine names moved from ai submodules to standalone contrib modules.
   *
   * @see composer.json
   */
  protected const STANDALONE_MODULES = [
    'ai_content_suggestions',
    'ai_logging',
    'ai_translate',
    'ai_validations',
    'field_widget_actions',
  ];

  /**
   * Tests that the standalone contrib module wins over the deprecated stub.
   *
   * Builds a virtual filesystem containing both the leftover submodule
   * (modules/contrib/ai/modules/<name>), which a site may still have on disk
   * from before this module stopped shipping it, and the standalone
   * contrib module (modules/contrib/<name>) fetched via composer. Drupal's
   * extension discovery must resolve to the standalone module in both
   * cases, regardless of the order the two directories are created in.
   */
  public function testStandaloneContribWinsOverDeprecatedStub(): void {
    foreach ([TRUE, FALSE] as $stub_created_first) {
      $vfs = vfsStream::setup('root', NULL, $this->buildFilesystemStructure($stub_created_first));
      $root = $vfs->url();

      $discovery = new ExtensionDiscovery($root, FALSE, [], 'sites/default');
      $modules = $discovery->scan('module', FALSE);

      foreach (static::STANDALONE_MODULES as $name) {
        $this->assertArrayHasKey($name, $modules, "Module $name was not discovered.");
        $this->assertSame(
          "modules/contrib/$name/$name.info.yml",
          $modules[$name]->getPathname(),
          "Module $name should resolve to the standalone contrib module."
        );
        $this->assertFileExists(
          $root . '/modules/contrib/' . $name . '/src/Marker.php',
          "Standalone contrib module $name should provide its own code."
        );
      }
    }
  }

  /**
   * Builds the fixture filesystem structure for vfsStream.
   *
   * @param bool $stub_first
   *   Whether the deprecated stub directories should be added to the
   *   structure array before the standalone contrib module directories.
   *
   * @return array
   *   A vfsStream-compatible nested filesystem structure.
   */
  protected function buildFilesystemStructure(bool $stub_first): array {
    $stub_modules = [];
    $standalone_modules = [];
    foreach (static::STANDALONE_MODULES as $name) {
      $stub_modules[$name] = [
        "$name.info.yml" => $this->buildInfoYml($name, deprecated: TRUE),
      ];
      $standalone_modules[$name] = [
        "$name.info.yml" => $this->buildInfoYml($name, deprecated: FALSE),
        'src' => [
          'Marker.php' => "<?php\n// Marks this copy as the standalone contrib module.\n",
        ],
      ];
    }

    $ai_module = [
      'ai.info.yml' => $this->buildInfoYml('ai', deprecated: FALSE),
      'modules' => $stub_modules,
    ];

    $contrib = $standalone_modules;
    if ($stub_first) {
      $contrib = ['ai' => $ai_module] + $contrib;
    }
    else {
      $contrib['ai'] = $ai_module;
    }

    return [
      'modules' => [
        'contrib' => $contrib,
      ],
    ];
  }

  /**
   * Builds the contents of a module .info.yml file.
   *
   * @param string $name
   *   The module machine name.
   * @param bool $deprecated
   *   Whether to mark the module as a deprecated leftover stub.
   *
   * @return string
   *   The YAML contents of the .info.yml file.
   */
  protected function buildInfoYml(string $name, bool $deprecated): string {
    $lines = [
      "name: '$name'",
      'type: module',
      "description: 'Test fixture for $name'",
      'core_version_requirement: ^10.4 || ^11',
    ];
    if ($deprecated) {
      $lines[] = 'lifecycle: deprecated';
    }
    return implode("\n", $lines) . "\n";
  }

}
