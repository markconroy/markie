<?php

namespace Drupal\Tests\stage_file_proxy\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\stage_file_proxy\EventSubscriber\StageFileProxySubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the redirect to the origin when hotlinking.
 */
#[CoversClass(StageFileProxySubscriber::class)]
#[Group('stage_file_proxy')]
#[RunTestsInSeparateProcesses]
class HotlinkTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'file', 'image', 'stage_file_proxy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // A web path rather than the test's vfs:// one; hotlinking writes no file.
    $this->setSetting('file_public_path', 'sites/default/files');
    $this->installConfig(['stage_file_proxy']);
    $this->config('stage_file_proxy.settings')
      ->set('origin', 'https://origin.example.com')
      ->set('hotlink', TRUE)
      ->save();
  }

  /**
   * Hotlinking redirects to the requested file, extension and all.
   */
  #[DataProvider('providerRequestedPath')]
  public function testHotlinkKeepsRequestedPath(string $relative_path, bool $use_imagecache_root): void {
    $this->config('stage_file_proxy.settings')->set('use_imagecache_root', $use_imagecache_root)->save();
    $request_stack = $this->container->get('request_stack');
    $file_dir = $this->container->get('stage_file_proxy.download_manager')->filePublicPath();

    $request = Request::create("http://localhost/$file_dir/$relative_path");
    $request->setSession($request_stack->getCurrentRequest()->getSession());
    $request_stack->push($request);
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get(StageFileProxySubscriber::class)->checkFileOrigin($event);

    $this->assertSame("https://origin.example.com/$file_dir/$relative_path", $event->getResponse()->headers->get('Location'));
  }

  /**
   * Provides the requested paths.
   */
  public static function providerRequestedPath(): array {
    $paths = [
      'filename with another dot' => 'banner_v2.6_2.jpg',
      'filename image style derivative with dot' => 'styles/large/public/banner_v2.6_2.jpg',
      'converted image style derivative' => 'styles/large/public/banner_v2.6_2.jpg.webp',
    ];
    $cases = [];
    foreach ($paths as $label => $path) {
      $cases["$label, imagecache root"] = [$path, TRUE];
      $cases[$label] = [$path, FALSE];
    }
    return $cases;
  }

}
