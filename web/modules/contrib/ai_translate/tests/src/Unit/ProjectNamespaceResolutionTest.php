<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_translate\Unit;

use Composer\Autoload\ClassLoader;
use Drupal\Tests\UnitTestCase;
use Drupal\ai_translate\TextExtractor;

/**
 * Tests that this project's classes take precedence over a duplicate copy.
 *
 * The AI module still bundles the deprecated ai_translate submodule that this
 * project replaced. Drupal core's PHPUnit bootstrap keys discovered extensions
 * by name, so only one directory per extension name is registered and the
 * bundled copy wins. Without the psr-4 entries in this project's composer.json,
 * unit tests either fail with "class not found" for classes that exist only
 * here, or - worse - silently exercise the bundled copy's code.
 *
 * Kernel and functional tests are unaffected, because they resolve classes
 * through the container using Drupal's own module list.
 *
 * @group ai_translate
 */
final class ProjectNamespaceResolutionTest extends UnitTestCase {

  /**
   * Tests that the module namespace resolves to this project first.
   */
  public function testModuleNamespaceResolvesToThisProjectFirst(): void {
    $this->assertNamespaceResolvesTo('Drupal\ai_translate\\', 'src');
  }

  /**
   * Tests that the test namespace resolves to this project first.
   */
  public function testTestNamespaceResolvesToThisProjectFirst(): void {
    $this->assertNamespaceResolvesTo('Drupal\Tests\ai_translate\\', 'tests/src');
  }

  /**
   * Tests that autoloading a module class loads this project's file.
   *
   * This is the end-to-end check: the two assertions above verify the
   * registered paths, this one verifies what the autoloader actually loads.
   */
  public function testModuleClassIsLoadedFromThisProject(): void {
    $resolved = (new \ReflectionClass(TextExtractor::class))->getFileName();

    $this->assertSame(
      realpath($this->getProjectRoot() . '/src/TextExtractor.php'),
      realpath((string) $resolved),
      'TextExtractor was autoloaded from outside this project, most likely from the ai_translate submodule bundled in the AI module. Check the psr-4 autoload entries in composer.json.'
    );
  }

  /**
   * Asserts that a namespace resolves to a directory in this project first.
   *
   * @param string $namespace
   *   The PSR-4 namespace prefix to check.
   * @param string $relativePath
   *   The directory this project expects to be searched first, relative to the
   *   project root.
   */
  protected function assertNamespaceResolvesTo(string $namespace, string $relativePath): void {
    $prefixes = $this->getClassLoader()->getPrefixesPsr4();

    $this->assertArrayHasKey($namespace, $prefixes, sprintf(
      'The namespace %s is not registered with the Composer autoloader at all.',
      $namespace
    ));

    $expected = realpath($this->getProjectRoot() . '/' . $relativePath);
    $this->assertNotFalse($expected, sprintf(
      'The directory %s does not exist in this project.',
      $relativePath
    ));

    $searchOrder = array_values($prefixes[$namespace]);
    $this->assertSame($expected, realpath($searchOrder[0]), sprintf(
      'The first directory searched for %s is not this project\'s %s. Full search order: %s',
      $namespace,
      $relativePath,
      implode(', ', $searchOrder)
    ));
  }

  /**
   * Returns the root directory of this project.
   *
   * @return string
   *   The absolute path to the project root.
   */
  protected function getProjectRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Returns the Composer class loader for this project.
   *
   * Core's PHPUnit bootstrap wraps the loader in Symfony's DebugClassLoader, so
   * the instance cannot be read back out of spl_autoload_functions().
   *
   * @return \Composer\Autoload\ClassLoader
   *   The class loader.
   */
  protected function getClassLoader(): ClassLoader {
    $loaders = ClassLoader::getRegisteredLoaders();
    $vendorDir = realpath($this->getProjectRoot() . '/vendor');

    foreach ($loaders as $registeredVendorDir => $loader) {
      if (realpath($registeredVendorDir) === $vendorDir) {
        return $loader;
      }
    }

    // Fall back to the only loader, for setups that install to a vendor
    // directory somewhere other than the project root.
    if (count($loaders) === 1) {
      return reset($loaders);
    }

    $this->fail(sprintf(
      'Unable to identify the Composer class loader for this project. Registered vendor directories: %s',
      implode(', ', array_keys($loaders)) ?: '(none)'
    ));
  }

}
