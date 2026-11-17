<?php

namespace Drupal\Tests\key\Unit;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * Provides a base class for key tests.
 */
abstract class KeyTestBase extends UnitTestCase {

  /**
   * Config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Configuration storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage
   */
  protected $configStorage;

  /**
   * Entity type manager instance.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Memory cache instance.
   *
   * @var \Drupal\Core\Cache\MemoryCache\MemoryCache
   */
  protected $memoryCache;

  /**
   * Container builder.
   *
   * This should be used sparingly by test cases to add to the container as
   * necessary for tests.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the Config object, but methods will be mocked in the test class.
    $this->config = $this->createStub('\Drupal\Core\Config\ImmutableConfig');

    // Mock ConfigEntityStorage object, but methods will be mocked in the test
    // class.
    $this->configStorage = $this->createStub('\Drupal\Core\Config\Entity\ConfigEntityStorage');

    // Mock EntityTypeManager service.
    $this->entityTypeManager = $this->createMock('\Drupal\Core\Entity\EntityTypeManager');
    $this->entityTypeManager
      ->method('getStorage')
      ->with('key')
      ->willReturn($this->configStorage);

    // Mock MemoryCache service.
    $this->memoryCache = $this->createStub('\Drupal\Core\Cache\MemoryCache\MemoryCache');

    // Create a dummy container.
    $this->container = new ContainerBuilder();
    $this->container->set('entity_type.manager', $this->entityTypeManager);
    $this->container->set('key.memory_cache', $this->memoryCache);

    // Each test class should call \Drupal::setContainer() in its own setUp
    // method so that test classes can add mocked services to the container
    // without affecting other test classes.
  }

  /**
   * Return a token that could be a key.
   *
   * @return string
   *   A hashed string that could be confused as some secret token.
   */
  protected function createToken() {
    return strtoupper(hash('ripemd128', Crypt::hashBase64($this->getRandomGenerator()->string(30))));
  }

}
