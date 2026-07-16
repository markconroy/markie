<?php

namespace Drupal\backup_migrate\Drupal\Source;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\backup_migrate\Core\Source\FileDirectorySource;
use Drupal\backup_migrate\Core\Source\SourceInterface;
use Drupal\backup_migrate\Core\File\BackupFileReadableInterface;

/**
 * Provides the drupal site archive source class.
 *
 * @package Drupal\backup_migrate\Drupal\Source
 */
class DrupalSiteArchiveSource extends FileDirectorySource {

  /**
   * Handles the construct operation.
   *
   * @param \Drupal\backup_migrate\Core\Config\ConfigInterface|array $init
   *   Initial configuration.
   * @param \Drupal\backup_migrate\Core\Source\SourceInterface $dbSource
   *   The database source.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   */
  public function __construct(
    $init,
    protected readonly SourceInterface $dbSource,
    protected readonly ?TimeInterface $time = NULL,
  ) {
    parent::__construct($init);
  }

  /**
   * Get a list if files to be backed up from the given directory.
   *
   * Do not include files that match the 'exclude_filepaths' setting.
   *
   * @param string $dir
   *   The dir.
   *   The name of the directory to list.
   *
   * @return array
   *   A render or configuration array.
   *
   * @throws \Drupal\backup_migrate\Core\Exception\BackupMigrateException
   * @throws \Drupal\backup_migrate\Core\Exception\IgnorableException
   *
   * @internal param $directory
   */
  protected function getFilesToBackup($dir) {
    $files = [];

    // Add the database dump.
    // @todo realpath contains the wrong filename and the PEAR archiver cannot rename files.
    $db = $this->getDbSource()->exportToFile();
    $files['database.sql'] = $db->realpath();

    // Add the manifest file.
    $manifest = $this->getManifestFile();
    $files['MANIFEST.ini'] = $manifest->realpath();

    // Get all the files in the site.
    foreach (parent::getFilesToBackup($dir) as $new => $real) {
      // Prepend 'docroot' onto the local path.
      $files['docroot/' . $new] = $real;
    }

    return $files;
  }

  /**
   * Import to this source from the given backup file.
   *
   * This is the main restore function for this source.
   *
   * @param \Drupal\backup_migrate\Core\File\BackupFileReadableInterface $file
   *   The backup file.
   *   The file to read the backup from. It will not be opened for reading.
   *
   * @return bool|void
   *   TRUE when successful, FALSE otherwise.
   */
  public function importFromFile(BackupFileReadableInterface $file) {
    // @todo Implement importFromFile() method.
  }

  /**
   * Get a file which contains the file.
   *
   * @return \Drupal\backup_migrate\Core\File\BackupFileWritableInterface
   *   The requested integer.
   */
  protected function getManifestFile() {
    $out = $this->getTempFileManager()->create('ini');

    $info = [
      'Global' => [
        'datestamp' => $this->time ? $this->time->getRequestTime() : time(),
        "formatversion" => "2011-07-02",
        "generator" => "Backup and Migrate (http://drupal.org/project/backup_migrate)",
        "generatorversion" => backup_migrate_module_version(),
      ],
      'Site 0' => [
        'version' => \Drupal::VERSION,
        'name' => "Example.com",
        'docroot' => "docroot",
        'sitedir' => "docroot/sites/default",
        'database-file-default' => "database.sql",
        'database-file-driver' => "mysql",
        'files-private' => "docroot/sites/default/private",
        'files-public' => "docroot/sites/default/files",
      ],
    ];

    $out->writeAll($this->arrayToIni($info));
    return $out;
  }

  /**
   * Translate a 2d array to an INI string which can be written to a file.
   *
   * @param array $info
   *   The array to convert. Must be an array of sections each of which is an
   *   array of field/value pairs.
   *
   * @return string
   *   *   The data in INI format.
   */
  private function arrayToIni(array $info) {
    $content = "";
    foreach ($info as $section => $data) {
      $content .= '[' . $section . ']' . "\n";
      foreach ($data as $key => $val) {
        $content .= $key . " = \"" . $val . "\"\n";
      }
      $content .= "\n";
    }
    return $content;

  }

  /**
   * Gets the database source.
   *
   * @return \Drupal\backup_migrate\Core\Source\SourceInterface
   *   The requested integer.
   */
  public function getDbSource() {
    return $this->dbSource;
  }

}
