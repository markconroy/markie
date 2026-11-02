<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_api_explorer\Kernel;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests storage of and access to explorer output files.
 *
 * @group ai_api_explorer
 */
#[Group('ai_api_explorer')]
#[RunTestsInSeparateProcesses]
class FileDownloadAccessTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'ai', 'ai_api_explorer'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system']);
    // Create the root user so test users don't bypass permissions.
    $this->createUser();
  }

  /**
   * Explorer output is only served to the user who generated it.
   */
  public function testFileDownloadAccess(): void {
    $owner = $this->setUpCurrentUser([], ['access ai prompt']);
    $uri = $this->saveOutput();

    // The owner can download it.
    $headers = $this->download($uri);
    $this->assertIsArray($headers);
    $this->assertSame('image/png', $headers['Content-Type']);

    // Another explorer user cannot.
    $this->setUpCurrentUser([], ['access ai prompt']);
    $this->assertNull($this->download($uri));

    // Anonymous visitors cannot.
    $this->setCurrentUser(new AnonymousUserSession());
    $this->assertNull($this->download($uri));

    // The owner cannot once they lose the explorer permission.
    $this->setCurrentUser($this->createUser([]));
    $own_uri = $this->saveOutput();
    $this->assertNull($this->download($own_uri));

    // A file in the directory without a file entity is not served.
    $this->setCurrentUser($owner);
    file_put_contents('temporary://ai-explorers/unmanaged.png', 'data');
    $this->assertNull($this->download('temporary://ai-explorers/unmanaged.png'));

    // Files outside the explorer directory are left to other modules.
    $this->assertNull(ai_api_explorer_file_download('temporary://other.png'));
    $this->assertNull(ai_api_explorer_file_download('temporary://ai-explorers-other/output.png'));
    $this->assertNull(ai_api_explorer_file_download('public://ai-explorers/output.png'));
  }

  /**
   * Output is a temporary file under an unguessable name, cleaned up by cron.
   */
  public function testSaveUsesTemporaryWithoutPrivatePath(): void {
    $owner = $this->setUpCurrentUser([], ['access ai prompt']);
    $url = $this->container->get('ai_api_explorer.output_files')->save('data', 'png');

    $this->assertStringContainsString('/system/temporary?file=ai-explorers/', $url);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    $this->assertMatchesRegularExpression('#^ai-explorers/[A-Za-z0-9_-]{43}\.png$#', $query['file']);

    $uri = 'temporary://' . $query['file'];
    $file = $this->container->get('file.repository')->loadByUri($uri);
    $this->assertNotNull($file);
    $this->assertTrue($file->isTemporary());
    $this->assertSame($owner->id(), $file->getOwnerId());

    // Cron removes the file once it is older than the temporary file age.
    $this->container->get('database')->update('file_managed')
      ->fields(['changed' => \Drupal::time()->getRequestTime() - 86400])
      ->condition('fid', $file->id())
      ->execute();
    $this->container->get('cron')->run();
    $this->assertNull($this->container->get('file.repository')->loadByUri($uri));
    $this->assertFileDoesNotExist($uri);
  }

  /**
   * Output goes to the private file system when one is configured.
   */
  public function testSaveUsesPrivatePath(): void {
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
    $this->container->get('kernel')->rebuildContainer();
    $this->container = $this->container->get('kernel')->getContainer();
    $this->container->get('stream_wrapper_manager')->register();

    $this->setUpCurrentUser([], ['access ai prompt']);
    $url = $this->container->get('ai_api_explorer.output_files')->save('data', 'mp3');

    $this->assertMatchesRegularExpression('#/system/files/ai-explorers/[A-Za-z0-9_-]{43}\.mp3$#', $url);
    $uri = 'private://ai-explorers/' . basename($url);
    $this->assertSame('data', file_get_contents($uri));
    $this->assertIsArray($this->download($uri));
  }

  /**
   * Saves explorer output as the current user.
   *
   * @return string
   *   The URI of the saved file.
   */
  protected function saveOutput(): string {
    $url = $this->container->get('ai_api_explorer.output_files')->save('data', 'png');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    return 'temporary://' . $query['file'];
  }

  /**
   * Resolves a download the way the file download controller does.
   *
   * @param string $uri
   *   The URI being accessed.
   *
   * @return array|null
   *   The headers if the file is served, NULL if access is denied.
   */
  protected function download(string $uri): ?array {
    $headers = $this->container->get('module_handler')->invokeAll('file_download', [$uri]);
    if (!$headers || in_array(-1, $headers, TRUE)) {
      return NULL;
    }
    return $headers;
  }

}
