<?php

namespace Drupal\upgrade_status\Commands;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\Extension;
use Drupal\upgrade_status\DeprecationAnalyzer;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ScanResultFormatter;
use Drush\Commands\DrushCommands;
use Drush\Drupal\DrupalUtil;

/**
 * Upgrade Status Drush command
 */
class UpgradeStatusCommands extends DrushCommands {

  /**
   * The scan result formatter service.
   *
   * @var \Drupal\upgrade_status\ScanResultFormatter
   */
  protected $resultFormatter;

  /**
   * The project collector service.
   *
   * @var \Drupal\upgrade_status\ProjectCollector
   */
  protected $projectCollector;

  /**
   * The codebase analyzer service.
   *
   * @var \Drupal\upgrade_status\DeprecationAnalyzer
   */
  protected $deprecationAnalyzer;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Output mode (format).
   *
   * @var string
   */
  protected $mode = 'ascii';

  /**
   * Constructs a new UpgradeStatusCommands object.
   *
   * @param \Drupal\upgrade_status\ScanResultFormatter $result_formatter
   *   The scan result formatter service.
   * @param \Drupal\upgrade_status\ProjectCollector $project_collector
   *   The project collector service.
   * @param \Drupal\upgrade_status\DeprecationAnalyzer $deprecation_analyzer
   *   The codebase analyzer service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    ScanResultFormatter $result_formatter,
    ProjectCollector $project_collector,
    DeprecationAnalyzer $deprecation_analyzer,
    DateFormatterInterface $date_formatter) {
    $this->projectCollector = $project_collector;
    $this->resultFormatter = $result_formatter;
    $this->deprecationAnalyzer = $deprecation_analyzer;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * Analyze projects output as XML.
   *
   * @param array $projects
   *   List of projects to analyze.
   * @param array $options
   *   Additional options for the command.
   *
   * @command upgrade_status:checkstyle
   * @option all Analyze all projects.
   * @option skip-existing Return results from a previous scan of a project if available, otherwise start a new one.
   * @option ignore-uninstalled Ignore uninstalled projects.
   * @option ignore-contrib Ignore contributed projects.
   * @option ignore-custom Ignore custom projects.
   * @aliases us-cs
   *
   * @throws \InvalidArgumentException
   *   Thrown when one of the passed arguments is invalid or no arguments were provided.
   */
  public function checkstyle(array $projects, array $options = ['all' => FALSE, 'skip-existing' => FALSE, 'ignore-uninstalled' => FALSE, 'ignore-contrib' => FALSE, 'ignore-custom' => FALSE]) {
    $this->mode = 'checkstyle';
    $this->analyze($projects, $options);
  }

  /**
   * Analyze projects output as ASCII.
   *
   * @param array $projects
   *   List of projects to analyze.
   * @param array $options
   *   Additional options for the command.
   *
   * @command upgrade_status:analyze
   * @option all Analyze all projects.
   * @option skip-existing Return results from a previous scan of a project if available, otherwise start a new one.
   * @option ignore-uninstalled Ignore uninstalled projects.
   * @option ignore-contrib Ignore contributed projects.
   * @option ignore-custom Ignore custom projects.
   * @aliases us-a
   *
   * @throws \InvalidArgumentException
   *   Thrown when one of the passed arguments is invalid or no arguments were provided.
   */
  public function analyze(array $projects, array $options = ['all' => FALSE, 'skip-existing' => FALSE, 'ignore-uninstalled' => FALSE, 'ignore-contrib' => FALSE, 'ignore-custom' => FALSE]) {
    // Group by type here so we can tell loader what is type of each one of
    // these.
    $extensions = [];
    $invalid_names = [];

    if (empty($projects) && !$options['all']) {
      $message = dt('You need to provide at least one installed project\'s machine_name.');
      throw new \InvalidArgumentException($message);
    }

    // Gather project list grouped by custom and contrib projects.
    $available_projects = $this->projectCollector->collectProjects();

    if ($options['all']) {
      foreach ($available_projects as $type => $projects) {
        if (!$options['ignore-' . $type]) {
          foreach ($projects as $name => $project) {
            if (!$options['ignore-uninstalled'] || $project->status !== 0) {
              $extensions[$project->getType()][$name] = $project;
            }
          }
        }
      }
    }
    else {
      foreach ($projects as $name) {
        if (!$options['ignore-custom'] && array_key_exists($name, $available_projects['custom'])) {
          $type = $available_projects['custom'][$name]->getType();
          if (!$options['ignore-uninstalled'] || $available_projects['custom'][$name]->status !== 0) {
            $extensions[$type][$name] = $available_projects['custom'][$name];
          }
          else {
            $invalid_names[] = $name;
          }
        }
        elseif (!$options['ignore-contrib'] && array_key_exists($name, $available_projects['contrib'])) {
          $type = $available_projects['contrib'][$name]->getType();
          if (!$options['ignore-uninstalled'] || $available_projects['contrib'][$name]->status !== 0) {
            $extensions[$type][$name] = $available_projects['contrib'][$name];
          }
          else {
            $invalid_names[] = $name;
          }
        }
        else {
          $invalid_names[] = $name;
        }
      }
    }

    if (!empty($invalid_names)) {
      if (count($invalid_names) == 1) {
        $message = dt('The project machine name @invalid_name is invalid. Is this a project on this site? (For community projects, use the machine name of the drupal.org project itself).', [
          '@invalid_name' => $invalid_names[0],
        ]);
      }
      else {
        $message = dt('The project machine names @invalid_names are invalid. Are these projects on this site? (For community projects, use the machine name of the drupal.org project itself).', [
          '@invalid_names' => implode(', ', $invalid_names),
        ]);
      }
      throw new \InvalidArgumentException($message);
    }
    else {
      $this->logger()->info(dt('Starting the analysis. This may take a while.'));
    }

    foreach ($extensions as $type => $list) {
      foreach ($list as $name => $extension) {
        if ($options['skip-existing']) {
          $scan_result = \Drupal::service('keyvalue')->get('upgrade_status_scan_results')->get($name);
          if (!empty($scan_result)) {
            $this->logger()->info(dt('Using previous results for @name.', ['@name' => $name]));
            continue;
          }
        }
        $this->logger()->info(dt('Processing @name.', ['@name' => $name]));
        $this->deprecationAnalyzer->analyze($extension);
      }
    }

    if ($this->mode !== 'ascii') {
      $xml = new \SimpleXMLElement("<?xml version='1.0'?><checkstyle/>");
    }

    foreach ($extensions as $type => $list) {
      if ($this->mode === 'ascii') {
        $this->output()->writeln('');
        $this->output()->writeln(str_pad('', 80, '='));
      }

      $track = 0;
      foreach ($list as $name => $extension) {

        $result = $this->resultFormatter->getRawResult($extension);

        if (is_null($result)) {
          $this->logger()
            ->error('Project scan @name failed.', ['@name' => $name]);
          continue;
        }

        if ($this->mode === 'ascii') {
          $output = $this->formatDrushStdoutResult($extension);
          foreach ($output as $line) {
            $this->output()->writeln($line);
          }
          if (++$track < count($list)) {
            $this->output()->writeln(str_pad('', 80, '='));
          }
        }
        else {
          foreach ($result['data']['files'] as $filepath => $errors) {
            $short_path = str_replace(DRUPAL_ROOT . '/', '', $filepath);
            $file_xml = $xml->addChild('file');
            $file_xml->addAttribute('name', $short_path);
            foreach ($errors['messages'] as $error) {
              $severity = 'error';
              if ($error['upgrade_status_category'] == 'ignore') {
                $severity = 'info';
              }
              elseif ($error['upgrade_status_category'] == 'later') {
                $severity = 'warning';
              }
              $error_xml = $file_xml->addChild('error');
              $error_xml->addAttribute('line', $error['line']);
              $error_xml->addAttribute('message', $error['message']);
              $error_xml->addAttribute('severity', $severity);
            }
          }
          $this->output()->writeln($xml->asXML());
        }
      }
    }
  }

  /**
   * Format results output for an extension for Drush STDOUT usage.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   Drupal extension objet.
   *
   * @return array
   *   Scan results formatted for output, per line.
   */
  public function formatDrushStdoutResult(Extension $extension) {
    $table = [];
    $result = $this->resultFormatter->getRawResult($extension);
    $info = $extension->info;

    $table[] = $info['name'] . ', ' . (!empty($info['version']) ? ' ' . $info['version'] : '--');
    $table[] = dt('Scanned on @date', [
      '@date' => $this->dateFormatter->format($result['date']),
    ]);

    if (isset($result['data']['totals'])) {
      $project_error_count = $result['data']['totals']['file_errors'];
    }
    else {
      $project_error_count = 0;
    }

    if (!$project_error_count || !is_array($result['data']['files'])) {
      $table[] = '';
      $table[] = dt('No known issues found.');
      $table[] = '';
      return $table;
    }

    foreach ($result['data']['files'] as $filepath => $errors) {
      // Remove the Drupal root directory name. If this is a composer setup,
      // then the webroot is in a web/ directory, add that back in for easy
      // path copy-pasting.
      $short_path = str_replace(DRUPAL_ROOT . '/', '', $filepath);
      if (preg_match('!/web$!', DRUPAL_ROOT)) {
        $short_path = 'web/' . $short_path;
      }
      $short_path = wordwrap(dt('FILE: ') . $short_path, 80, "\n", TRUE);

      $table[] = '';
      $table[] = $short_path;
      $table[] = '';
      $title_level = str_pad(dt('STATUS'), 15, ' ');
      $title_line = str_pad(dt('LINE'), 5, ' ');
      $title_msg = str_pad(dt('MESSAGE'), 60, ' ', STR_PAD_BOTH);
      $table[] = $title_level . $title_line . $title_msg;

      foreach ($errors['messages'] as $error) {
        $table[] = str_pad('', 80, '-');
        $error['message'] = str_replace("\n", ' ', $error['message']);
        $error['message'] = str_replace('  ', ' ', $error['message']);
        $error['message'] = trim($error['message']);

        $level_label = dt('Check manually');
        if ($error['upgrade_status_category'] == 'ignore') {
          $level_label = dt('Ignore');
        }
        elseif ($error['upgrade_status_category'] == 'later') {
          $level_label = dt('Fix later');
        }
        elseif (in_array($error['upgrade_status_category'], ['safe', 'old'])) {
          $level_label = dt('Fix now');
        }
        $linecount = 0;

        $msg_parts = explode("\n", wordwrap($error['message'], 60, "\n", TRUE));
        foreach ($msg_parts as $msg_part) {
          $msg_part = str_pad($msg_part, 60, ' ');
          if (!$linecount++) {
            $level_label = str_pad(substr($level_label, 0, 15), '15', ' ');
            $line = str_pad($error['line'], 5, ' ');
          }
          else {
            $level_label = str_pad(substr('', 0, 15), '15', ' ');
            $line = str_pad('', 5, ' ');
          }
          $table[] = $level_label . $line . $msg_part;
        }
      }

      $table[] = str_pad('', 80, '-');
    }

    if (!empty($result['plans'])) {
      $table[] = '';
      $table[] = DrupalUtil::drushRender($result['plans']);
    }
    $table[] = '';

    return $table;
  }

}
