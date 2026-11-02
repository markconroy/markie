<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\file\FileRepositoryInterface;

/**
 * Stores files generated in the explorers and controls access to them.
 *
 * Files are saved as temporary managed files owned by the current user, in
 * private://ai-explorers/, or temporary://ai-explorers/ when no private file
 * path is configured. The file module then only serves them to their owner,
 * and cron removes them once they are older than the temporary file age.
 */
class ExplorerOutputFiles {

  /**
   * The directory, inside the scheme, that holds the generated files.
   */
  const DIRECTORY = 'ai-explorers';

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected FileRepositoryInterface $fileRepository,
    protected AccountProxyInterface $currentUser,
    protected StreamWrapperManagerInterface $streamWrapperManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {
  }

  /**
   * Saves generated data for the current user.
   *
   * @param string $data
   *   The binary data.
   * @param string $extension
   *   The file extension, without the dot.
   *
   * @return string
   *   The URL the current user can load the file from.
   */
  public function save(string $data, string $extension): string {
    $scheme = $this->streamWrapperManager->isValidScheme('private') ? 'private' : 'temporary';
    $directory = $scheme . '://' . self::DIRECTORY;
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $file = $this->fileRepository->writeData($data, $directory . '/' . Crypt::randomBytesBase64() . '.' . $extension, FileExists::Rename);
    // Temporary, so only the owner can download it and cron cleans it up.
    $file->setTemporary();
    $file->save();
    return $this->fileUrlGenerator->generateString($file->getFileUri());
  }

  /**
   * Checks download access to an explorer file, for hook_file_download().
   *
   * Ownership is checked by the file module; this only adds the explorer
   * permission on top of it.
   *
   * @param string $uri
   *   The URI being accessed.
   *
   * @return int|null
   *   -1 if the current user may not use the explorers, NULL otherwise.
   */
  public function checkDownloadAccess(string $uri): ?int {
    if (!in_array(StreamWrapperManager::getScheme($uri), ['private', 'temporary'], TRUE)) {
      return NULL;
    }
    $target = StreamWrapperManager::getTarget($uri);
    if (!is_string($target) || !str_starts_with($target, self::DIRECTORY . '/')) {
      return NULL;
    }
    return $this->currentUser->hasPermission('access ai prompt') ? NULL : -1;
  }

}
