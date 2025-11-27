<?php

namespace Drupal\stage_file_proxy\Drush\Commands;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\stage_file_proxy\DownloadManagerInterface;
use Drush\Commands\DrushCommands;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Drush commands for Stage File Proxy.
 */
class StageFileProxyCommands extends DrushCommands {

  /**
   * The module config.
   *
   * Not called "config": name is used by Drush to store a DrushConfig instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $moduleConfig;

  /**
   * StageFileProxyCommands constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\stage_file_proxy\DownloadManagerInterface $downloadManager
   *   The stage_file_proxy.download_manager service.
   * @param string $root
   *   The app root.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected Connection $database,
    protected DownloadManagerInterface $downloadManager,
    protected string $root,
  ) {
    parent::__construct();

    $this->moduleConfig = $configFactory->get('stage_file_proxy.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('database'),
      $container->get('stage_file_proxy.download_manager'),
      '%app.root%',
    );
  }

  /**
   * Download all managed files from the origin.
   *
   * @command stage_file_proxy:dl
   * @aliases stage-file-proxy-dl,sfdl
   * @option skip-progress-bar Skip displaying a progress bar.
   * @option fid Only download the file that has this file id.
   */
  public function dl(
    array $command_options = [
      'skip-progress-bar' => FALSE,
      'fid' => 0,
    ],
  ): void {
    $logger = $this->logger();
    $server = $this->moduleConfig->get('origin');
    if (empty($server)) {
      throw new \Exception('Configure stage_file_proxy.settings.origin in your settings.php (see INSTALL.txt).');
    }

    $query = $this->database->select('file_managed', 'fm')
      ->fields('fm', ['uri'])
      ->orderBy('fm.fid', 'DESC');

    $fid = $command_options['fid'];
    if ($fid > 0) {
      $query->condition('fm.fid', $fid);
    }

    $results = $query->execute()
      ->fetchCol();

    $fileDir = $this->downloadManager->filePublicPath();
    $remoteFileDir = trim($this->moduleConfig->get('origin_dir'));
    if (!$remoteFileDir) {
      $remoteFileDir = $fileDir;
    }

    $gotFilesNumber = 0;
    $errorFilesNumber = 0;
    $notPublicFilesNumber = 0;
    $results_number = count($results);

    $publicPrefix = 'public://';
    $logger->notice('Downloading {count} files.', [
      'count' => $results_number,
    ]);
    $options = [
      'verify' => $this->moduleConfig->get('verify'),
    ];

    $progress_bar = NULL;
    if (!$command_options['skip-progress-bar']) {
      $progress_bar = new ProgressBar($this->output(), $results_number);
    }
    foreach ($results as $uri) {
      if (!str_starts_with($uri, $publicPrefix)) {
        $notPublicFilesNumber++;
        $progress_bar?->advance();
        continue;
      }

      $relativePath = mb_substr($uri, mb_strlen($publicPrefix));

      if (file_exists("{$this->root}/{$fileDir}/{$relativePath}")) {
        $progress_bar?->advance();
        continue;
      }

      try {
        if ($this->downloadManager->fetch($server, $remoteFileDir, $relativePath, $options)) {
          $gotFilesNumber++;
        }
        else {
          $errorFilesNumber++;
          $logger->error('Stage File Proxy encountered an unknown error by retrieving file {file}', [
            'file' => $server . '/' . UrlHelper::encodePath("{$remoteFileDir}/{$relativePath}"),
          ]);
        }
      }
      catch (ClientException $e) {
        $errorFilesNumber++;
        $logger->error($e->getMessage());
      }

      if ($progress_bar) {
        $progress_bar->advance();
      }
    }

    if ($progress_bar) {
      $progress_bar->finish();
    }

    $logger->notice('{gotFilesNumber} downloaded files.', [
      'gotFilesNumber' => $gotFilesNumber,
    ]);

    if ($errorFilesNumber) {
      $logger->error('{count} file(s) having an error, see log.', [
        'count' => $errorFilesNumber,
      ]);
    }

    if ($notPublicFilesNumber) {
      $logger->error('{count} file(s) not in public directory.', [
        'count' => $notPublicFilesNumber,
      ]);
    }
  }

}
