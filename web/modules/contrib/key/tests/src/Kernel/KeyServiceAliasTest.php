<?php

declare(strict_types=1);

namespace Drupal\Tests\key\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\key\KeyConfigOverrides;
use Drupal\key\KeyRepository;
use Drupal\key\KeyRepositoryInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the autowiring aliases for Key services.
 *
 * @group key
 */
#[Group('key')]
#[RunTestsInSeparateProcesses]
final class KeyServiceAliasTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['key'];

  /**
   * Tests that each autowiring alias resolves to its named service.
   */
  public function testAutowireAliases(): void {
    $aliases = [
      KeyRepositoryInterface::class => 'key.repository',
      KeyRepository::class => 'key.repository',
      KeyConfigOverrides::class => 'key.config_override',
    ];
    foreach ($aliases as $alias => $service_id) {
      $this->assertTrue($this->container->has($alias), "The $alias alias is defined.");
      $this->assertSame($this->container->get($service_id), $this->container->get($alias), "The $alias alias resolves to $service_id.");
    }
  }

}
