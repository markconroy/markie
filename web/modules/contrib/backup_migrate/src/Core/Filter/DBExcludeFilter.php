<?php

namespace Drupal\backup_migrate\Core\Filter;

use Drupal\backup_migrate\Core\Config\Config;
use Drupal\backup_migrate\Core\Plugin\PluginBase;
use Drupal\backup_migrate\Core\Plugin\PluginManager;
use Drupal\backup_migrate\Core\Source\DatabaseSourceInterface;

/**
 * Allows the exclusion of certain data from a database.
 *
 * @package Drupal\backup_migrate\Core\Filter
 */
class DBExcludeFilter extends PluginBase {

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Plugin\PluginManager The source manager
   */
  protected $sourceManager;

  /**
   * The compiled patterns.
   *
   * @var array
   *   A cache of compiled patterns.
   */
  public $patterns;

  /**
   * The 'beforeDbTableBackup' plugin op.
   *
   * @param array $table
   *   The table.
   * @param array $params
   *   The message parameters.
   *
   * @return array
   *   $table
   */
  public function beforeDbTableBackup(array $table, array $params = []) {
    $exclude = $this->confGet('exclude_tables');
    $nodata = $this->confGet('nodata_tables');
    $patterns = $this->confGet('exclude_patterns');
    $patterns = $this->globToRegex($patterns);
    if (in_array($table['name'], $exclude)) {
      $table['exclude'] = TRUE;
    }
    if (in_array($table['name'], $nodata)) {
      $table['nodata'] = TRUE;
    }
    if ($this->matchesPattern($table['name'], $patterns)) {
      $table['nodata'] = TRUE;
    }
    return $table;
  }

  /**
   * Convert an array of glob patterns to an array of regex patterns.
   *
   * Used for table name exclusion.
   *
   * @param array $exclude
   *   A list of patterns with glob wildcards.
   *
   * @return array
   *   A list of patterns as regular expressions
   */
  private function globToRegex(array $exclude): array {
    if ($this->patterns !== NULL) {
      return $this->patterns;
    }
    foreach ($exclude as $pattern) {
      // Convert Glob wildcards to a regex.
      // @see http://php.net/manual/en/function.fnmatch.php#71725
      $this->patterns[] = "#^" . strtr(preg_quote($pattern, '#'), [
        '\*' => '.*',
        '\?' => '.',
        '\[' => '[',
        '\]' => ']',
      ]) . "$#i";
    }
    return $this->patterns;
  }

  /**
   * Match a table name to the list of exclude patterns.
   *
   * @param string $tableName
   *   The table name to match.
   * @param array $patterns
   *   An array of regular expressions to match against.
   *
   * @return bool
   *   Whether or not the table names matches against at least one pattern.
   */
  private function matchesPattern($tableName, array $patterns): bool {
    if ($patterns) {
      foreach ($patterns as $pattern) {
        if (preg_match($pattern, $tableName)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Get the default values for the plugin.
   *
   * @return \Drupal\backup_migrate\Core\Config\Config
   *   The return value.
   */
  public function configDefaults() {
    return new Config([
      'source' => '',
      'exclude_tables' => [],
      'nodata_tables' => [],
      'exclude_patterns' => [
        'cache_*',
        'search_api_db_*',
      ],
    ]);
  }

  /**
   * Get a definition for user-configurable settings.
   *
   * @param array $params
   *   The message parameters.
   *
   * @return array
   *   A render or configuration array.
   */
  public function configSchema(array $params = []) {
    $schema = [];

    if ($params['operation'] == 'backup') {
      $tables = [];

      foreach ($this->sources()->getAll() as $source) {
        if ($source instanceof DatabaseSourceInterface) {
          $tables += $source->getTableNames();
        }

        if ($tables) {
          // Backup settings.
          $schema['groups']['default'] = [
            'title' => $this->t('Exclude database tables'),
          ];

          $table_select = [
            'type' => 'enum',
            'multiple' => TRUE,
            'options' => $tables,
            'actions' => ['backup'],
            'group' => 'default',
          ];
          $schema['fields']['exclude_tables'] = $table_select + [
            'title' => $this->t('Exclude these tables entirely'),
          ];

          $schema['fields']['nodata_tables'] = $table_select + [
            'title' => $this->t('Exclude data from these tables'),
          ];

          $schema['fields']['exclude_patterns'] = [
            'type' => 'text',
            'title' => $this->t('Exclude data from the tables matching these patterns'),
            'multiple' => TRUE,
            'group' => 'default',
          ];

        }
      }
    }
    return $schema;
  }

  /**
   * Handles the sources operation.
   *
   * @return \Drupal\backup_migrate\Core\Plugin\PluginManager
   *   The return value.
   */
  public function sources() {
    return $this->sourceManager ? $this->sourceManager : new PluginManager();
  }

  /**
   * Sets the source manager.
   *
   * @param \Drupal\backup_migrate\Core\Plugin\PluginManager $sourceManager
   *   The source manager.
   */
  public function setSourceManager(PluginManager $sourceManager) {
    $this->sourceManager = $sourceManager;
  }

}
