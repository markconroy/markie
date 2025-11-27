<?php

namespace Drupal\Tests\stage_file_proxy\Kernel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\stage_file_proxy\DownloadManager;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Test stage file proxy module.
 *
 * @coversDefaultClass \Drupal\stage_file_proxy\DownloadManager
 *
 * @group stage_file_proxy
 */
class DownloadManagerTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['system', 'file'];

  /**
   * Guzzle client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $client;

  /**
   * The file logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Filesystem interface.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected mixed $fileSystem;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The download manager.
   *
   * @var \Drupal\stage_file_proxy\DownloadManagerInterface
   */
  protected DownloadManager $downloadManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * Before a test method is run, setUp() is invoked.
   *
   * Create new downloadManager object.
   */
  public function setUp(): void {
    parent::setUp();

    $this->fileSystem = $this->container->get('file_system');
    $this->config('system.file')->set('default_scheme', 'public')->save();
    $this->client = new Client();
    $this->logger = \Drupal::logger('test_logger');
    $this->configFactory = $this->container->get('config.factory');
    $this->requestStack = new RequestStack();
    $this->downloadManager = new DownloadManager($this->client, $this->fileSystem, $this->logger, $this->configFactory, \Drupal::lock(), $this->requestStack);
  }

  /**
   * @covers \Drupal\stage_file_proxy\DownloadManager::styleOriginalPath
   */
  public function testStyleOriginalPath(): void {
    // Test image style path assuming public file scheme.
    $this->assertEquals('public://example.jpg', $this->downloadManager->styleOriginalPath('styles/icon_50x50_/public/example.jpg'));
  }

  /**
   * Clean up.
   *
   * Once test method has finished running, whether it succeeded or failed,
   * tearDown() will be invoked. Unset the $downloadManager object.
   */
  public function tearDown(): void {
    parent::tearDown();
    unset($this->downloadManager);
  }

}
