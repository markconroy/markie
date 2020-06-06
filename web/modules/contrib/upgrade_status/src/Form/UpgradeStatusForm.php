<?php

namespace Drupal\upgrade_status\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RedirectDestination;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\upgrade_status\DeprecationAnalyzer;
use Drupal\upgrade_status\ProjectCollector;
use Drupal\upgrade_status\ScanResultFormatter;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class UpgradeStatusForm extends FormBase {

  /**
   * The project collector service.
   *
   * @var \Drupal\upgrade_status\ProjectCollector
   */
  protected $projectCollector;

  /**
   * Available releases store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface|mixed
   */
  protected $releaseStore;

  /**
   * The scan result formatter service.
   *
   * @var \Drupal\upgrade_status\ScanResultFormatter
   */
  protected $resultFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * The deprecation analyzer.
   *
   * @var \Drupal\upgrade_status\DeprecationAnalyzer
   */
  protected $deprecationAnalyzer;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestination
   */
  protected $destination;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('upgrade_status.project_collector'),
      $container->get('keyvalue.expirable'),
      $container->get('upgrade_status.result_formatter'),
      $container->get('renderer'),
      $container->get('logger.channel.upgrade_status'),
      $container->get('module_handler'),
      $container->get('upgrade_status.deprecation_analyzer'),
      $container->get('state'),
      $container->get('date.formatter'),
      $container->get('redirect.destination')
    );
  }

  /**
   * Constructs a Drupal\upgrade_status\Form\UpgradeStatusForm.
   *
   * @param \Drupal\upgrade_status\ProjectCollector $project_collector
   *   The project collector service.
   * @param \Drupal\Core\KeyValueStore\KeyValueExpirableFactory $key_value_expirable
   *   The expirable key/value storage.
   * @param \Drupal\upgrade_status\ScanResultFormatter $result_formatter
   *   The scan result formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Extension\ModuleHandler $module_handler
   *   The module handler.
   * @param \Drupal\upgrade_status\DeprecationAnalyzer $deprecation_analyzer
   *   The deprecation analyzer.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Datetime\DateFormatter $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Routing\RedirectDestination $destination
   *   The destination service.
   */
  public function __construct(
    ProjectCollector $project_collector,
    KeyValueExpirableFactory $key_value_expirable,
    ScanResultFormatter $result_formatter,
    RendererInterface $renderer,
    LoggerInterface $logger,
    ModuleHandler $module_handler,
    DeprecationAnalyzer $deprecation_analyzer,
    StateInterface $state,
    DateFormatter $date_formatter,
    RedirectDestination $destination
  ) {
    $this->projectCollector = $project_collector;
    $this->releaseStore = $key_value_expirable->get('update_available_releases');
    $this->resultFormatter = $result_formatter;
    $this->renderer = $renderer;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->deprecationAnalyzer = $deprecation_analyzer;
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
    $this->destination = $destination;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'drupal_upgrade_status_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'upgrade_status/upgrade_status.admin';

    $analyzerReady = TRUE;
    try {
      $this->deprecationAnalyzer->initEnvironment();
    }
    catch (\Exception $e) {
      $analyzerReady = FALSE;
      $this->messenger()->addError($e->getMessage());
    }

    $last = $this->state->get('update.last_check') ?: 0;
    if ($last == 0) {
      $last_checked = '<strong>' . $this->t('Available update data not available.') . '</strong>';
    }
    else {
      $time = $this->dateFormatter->formatTimeDiffSince($last);
      $last_checked = $this->t('Available update data last checked: @time ago.', ['@time' => $time]);
    }
    $form['update_time'] = [
      [
        '#type' => 'markup',
        '#markup' => $last_checked . ' ',
      ],
      [
        '#type' => 'link',
        '#title' => '(' . $this->t('Check manually') . ')',
        '#url' => Url::fromRoute('update.manual_status', [], ['query' => $this->destination->getAsArray()]),
      ],
    ];

    $form['environment'] = [
      '#type' => 'details',
      '#title' => $this->t('Drupal core and hosting environment'),
      '#description' => $this->t('<a href=":upgrade">Upgrades to Drupal 9 are supported from Drupal 8.8.x and Drupal 8.9.x</a>. It is suggested to update to the latest Drupal 8 version available. <a href=":platform">Several hosting platform requirements have been raised for Drupal 9</a>.', [':upgrade' => 'https://www.drupal.org/docs/9/how-to-prepare-your-drupal-7-or-8-site-for-drupal-9/upgrading-a-drupal-8-site-to-drupal-9', ':platform' => 'https://www.drupal.org/docs/9/how-drupal-9-is-made-and-what-is-included/environment-requirements-of-drupal-9']),
      '#open' => TRUE,
      '#attributes' => ['class' => ['upgrade-status-summary upgrade-status-summary-environment']],
      'data' => $this->buildEnvironmentChecks(),
      '#tree' => TRUE,
    ];

    // Gather project list grouped by custom and contrib projects.
    $projects = $this->projectCollector->collectProjects();

    // List custom project status first.
    $custom = ['#type' => 'markup', '#markup' => '<br /><strong>' . $this->t('No custom projects found.') . '</strong>'];
    if (count($projects['custom'])) {
      $custom = $this->buildProjectList($projects['custom']);
    }
    $form['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Custom projects'),
      '#description' => $this->t('Custom code is specific to your site, and must be upgraded manually. <a href=":upgrade">Read more about how developers can upgrade their code to Drupal 9</a>.', [':upgrade' => 'https://www.drupal.org/docs/9/how-drupal-9-is-made-and-what-is-included/how-and-why-we-deprecate-on-the-way-to-drupal-9']),
      '#open' => TRUE,
      '#attributes' => ['class' => ['upgrade-status-summary upgrade-status-summary-custom']],
      'data' => $custom,
      '#tree' => TRUE,
    ];

    // List contrib project status second.
    $contrib = ['#type' => 'markup', '#markup' => '<br /><strong>' . $this->t('No contributed projects found.') . '</strong>'];
    if (count($projects['contrib'])) {
      $contrib = $this->buildProjectList($projects['contrib'], TRUE);
    }
    $form['contrib'] = [
      '#type' => 'details',
      '#title' => $this->t('Contributed projects'),
      '#description' => $this->t('Contributed code is available from drupal.org. Problems here may be partially resolved by updating to the latest version. <a href=":update">Read more about how to update contributed projects</a>.', [':update' => 'https://www.drupal.org/docs/8/update/update-modules']),
      '#open' => TRUE,
      '#attributes' => ['class' => ['upgrade-status-summary upgrade-status-summary-contrib']],
      'data' => $contrib,
      '#tree' => TRUE,
    ];

    $form['drupal_upgrade_status_form']['action']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Scan selected'),
      '#weight' => 2,
      '#button_type' => 'primary',
      '#disabled' => !$analyzerReady,
    ];
    $form['drupal_upgrade_status_form']['action']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export selected as HTML'),
      '#weight' => 5,
      '#submit' => [[$this, 'exportReportHTML']],
      '#disabled' => !$analyzerReady,
    ];
    $form['drupal_upgrade_status_form']['action']['export_ascii'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export selected as text'),
      '#weight' => 6,
      '#submit' => [[$this, 'exportReportASCII']],
      '#disabled' => !$analyzerReady,
    ];

    return $form;
  }

  /**
   * Builds a list and status summary of projects.
   *
   * @param \Drupal\Core\Extension\Extension[] $projects
   *   Array of extensions representing projects.
   * @param bool $isContrib
   *   (Optional) Whether the list to be produced is for contributed projects.
   *
   * @return array
   *   Build array.
   */
  protected function buildProjectList(array $projects, bool $isContrib = FALSE) {
    $counters = [
      'not-scanned' => 0,
      'no-known-error' => 0,
      'known-errors' => 0,
      'known-warnings' => 0,
      'known-error-projects' => 0,
      'known-warning-projects' => 0,
    ];

    $header = ['project' => ['data' => $this->t('Project'), 'class' => 'project-label']];
    if ($isContrib) {
      $header['update'] = ['data' => $this->t('Available update'), 'class' => 'update-info'];
    }
    $header['status'] = ['data' => $this->t('Status'), 'class' => 'status-info'];

    $build['uninstalled'] = $build['installed'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#weight' => 20,
      '#options' => [],
    ];
    $build['uninstalled']['#weight'] = '40';

    $update_check_for_uninstalled = $this->config('update.settings')->get('check.disabled_extensions');
    foreach ($projects as $name => $extension) {
      // Always use a fresh service. An injected service could get stale results
      // because scan result saving happens in different HTTP requests for most
      // cases (when analysis was successful).
      $scan_result = \Drupal::service('keyvalue')->get('upgrade_status_scan_results')->get($name);
      $info = $extension->info;
      $label = $info['name'] . (!empty($info['version']) ? ' ' . $info['version'] : '');
      $state = empty($extension->status) ? 'uninstalled' : 'installed';

      $update_cell = [
        'class' => 'update-info',
        'data' => $isContrib ? $this->t('Up to date') : '',
      ];
      $label_cell = [
        'data' => [
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#value' => $label,
            '#attributes' => [
              'for' => 'edit-' . ($isContrib ? 'contrib' : 'custom') . '-data-data-' . str_replace('_', '-', $name),
            ],
          ],
        ],
        'class' => 'project-label',
      ];

      if ($isContrib) {
        $projectUpdateData = $this->releaseStore->get($name);
        if (!isset($projectUpdateData['releases']) || is_null($projectUpdateData['releases'])) {
          $update_cell = ['class' => 'update-info', 'data' => $update_check_for_uninstalled ? $this->t('Not available') : $this->t('Not checked')];
        }
        else {
          $latestRelease = reset($projectUpdateData['releases']);
          $latestVersion = $latestRelease['version'];

          if ($info['version'] !== $latestVersion) {
            $link = $projectUpdateData['link'] . '/releases/' . $latestVersion;
            $update_cell = [
              'class' => 'update-info',
              'data' => [
                '#type' => 'link',
                '#title' => $latestVersion,
                '#url' => Url::fromUri($link),
              ]
            ];
          }
        }
      }

      // If this project was not found in our keyvalue storage, it is not yet scanned, report that.
      if (empty($scan_result)) {
        $build[$state]['#options'][$name] = [
          '#attributes' => ['class' => ['not-scanned', 'project-' . $name]],
          'project' => $label_cell,
          'update' => $update_cell,
          'status' => ['class' => 'status-info', 'data' => $this->t('Not scanned')],
        ];
        $counters['not-scanned']++;
        continue;
      }

      // Unpack JSON of deprecations to display results.
      $report = json_decode($scan_result, TRUE);

      if (!empty($report['plans'])) {
        $label_cell['data']['plans'] = [
          '#type' => 'markup',
          '#markup' => '<div>' . $report['plans'] . '</div>'
        ];
      }

      if (isset($report['data']['totals'])) {
        $project_error_count = $report['data']['totals']['file_errors'];
      }
      else {
        $project_error_count = 0;
      }

      // If this project had no known issues found, report that.
      if ($project_error_count === 0) {
        $build[$state]['#options'][$name] = [
          '#attributes' => ['class' => ['no-known-error', 'project-' . $name]],
          'project' => $label_cell,
          'update' => $update_cell,
          'status' => ['class' => 'status-info', 'data' => $this->t('No known errors')],
        ];
        $counters['no-known-error']++;
        continue;
      }

      // Finally this project had errors found, display them.
      $error_label = [];
      $error_class = 'known-warnings';
      if (!empty($report['data']['totals']['upgrade_status_split']['error'])) {
        $counters['known-errors'] += $report['data']['totals']['upgrade_status_split']['error'];
        $counters['known-error-projects']++;
        $error_class = 'known-errors';
        $error_label[] = $this->formatPlural(
          $report['data']['totals']['upgrade_status_split']['error'],
          '@count error',
          '@count errors'
        );
      }
      if (!empty($report['data']['totals']['upgrade_status_split']['warning'])) {
        $counters['known-warnings'] += $report['data']['totals']['upgrade_status_split']['warning'];
        $counters['known-warning-projects']++;
        $error_label[] = $this->formatPlural(
          $report['data']['totals']['upgrade_status_split']['warning'],
          '@count warning',
          '@count warnings'
        );
      }
      // If the project was declared Drupal 9 compatible (info and composer
      // files), than use that to visually display it as such. We still list
      // errors but they may be false positives or results of workaround code.
      if (!empty($report['data']['totals']['upgrade_status_split']['declared_ready'])) {
        $error_class = 'no-known-error';
      }
      $build[$state]['#options'][$name] = [
        '#attributes' => ['class' => [$error_class, 'project-' . $name]],
        'project' => $label_cell,
        'update' => $update_cell,
        'status' => [
          'class' => 'status-info',
          'data' => [
            '#type' => 'link',
            '#title' => join(', ', $error_label),
            '#url' => Url::fromRoute('upgrade_status.project', ['type' => $extension->getType(), 'project_machine_name' => $name]),
            '#attributes' => [
              'class' => ['use-ajax'],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode([
                'width' => 1024,
                'height' => 568,
              ]),
            ],
          ]
        ],
      ];
    }

    if (!$isContrib) {
      // If the list is not for contrib, remove the update placeholder.
      $states = ['installed', 'uninstalled'];
      foreach ($states as $state) {
        foreach ($build[$state]['#options'] as $name => &$row) {
          if (is_array($row)) {
            unset($row['update']);
          }
        }
      }
    }

    if (empty($build['uninstalled']['#options'])) {
      unset($build['uninstalled']);
    }
    else {
      // Add a specific intro section to uninstalled extensions.
      $check_info = '';
      if ($isContrib) {
        // Contrib extensions that are uninstalled may not get available updates info.
        $enable_check = Url::fromRoute('update.settings', [], ['query' => $this->destination->getAsArray()])->toString();
        if (!empty($update_check_for_uninstalled)) {
          $check_info = ' ' . $this->t('Available update checking for uninstalled extensions is enabled.');
        }
        else {
          $check_info = ' ' . $this->t('Available update checking for uninstalled extensions is disabled. (<a href="@enable">Enable</a>)', ['@enable' => $enable_check]);
        }
      }
      $build['uninstalled_intro'] = [
        '#weight' => 30,
        [
          '#type' => 'markup',
          '#markup' => '<h3>' . $this->t('Uninstalled extensions') . '</h3>'
        ],
        [
          '#type' => 'markup',
          '#markup' => '<div>' . $this->t('Consider if you need these uninstalled extensions at all. A limited set of checks may also be run on uninstalled extensions.') . $check_info . '</div>',
        ]
      ];
    }
    if (empty($build['installed']['#options'])) {
      unset($build['installed']);
    }

    $summary = [];

    if ($counters['known-errors'] > 0) {
      $summary[] = [
        'type' => $this->formatPlural($counters['known-errors'], '1 error', '@count errors'),
        'class' => 'error',
        'message' => $this->formatPlural($counters['known-error-projects'], 'Found in one project.', 'Found in @count projects.')
      ];
    }
    if ($counters['known-warnings'] > 0) {
      $summary[] = [
        'type' => $this->formatPlural($counters['known-warnings'], '1 warning', '@count warnings'),
        'class' => 'warning',
        'message' => $this->formatPlural($counters['known-warning-projects'], 'Found in one project.', 'Found in @count projects.')
      ];
    }
    if ($counters['no-known-error'] > 0) {
      $summary[] = [
        'type' => $this->formatPlural($counters['no-known-error'], '1 checked', '@count checked'),
        'class' => 'checked',
        'message' => $this->t('No known errors found.')
      ];
    }
    if ($counters['not-scanned'] > 0) {
      $summary[] = [
        'type' => $this->formatPlural($counters['not-scanned'], '1 not scanned', '@count not scanned'),
        'class' => 'not-scanned',
        'message' => $this->t('Scan to find errors.')
      ];
    }

    $build['summary'] = [
      '#theme' => 'upgrade_status_summary_counter',
      '#summary' => $summary
    ];

    return $build;
  }

  /**
   * Builds a list of environment checks.
   *
   * @return array
   *   Build array.
   */
  protected function buildEnvironmentChecks() {
    $header = [
      'requirement' => ['data' => $this->t('Requirement'), 'class' => 'requirement-label'],
      'status' => ['data' => $this->t('Status'), 'class' => 'status-info'],
    ];
    $build['data'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => [],
    ];

    // Check Drupal version. Link to update if available.
    $core_version_info = [
      '#type' => 'markup',
      '#markup' => $this->t('Version @version and up to date.', ['@version' => \Drupal::VERSION]),
    ];
    $has_core_update = FALSE;
    $core_update_info = $this->releaseStore->get('drupal');
    if (isset($core_update_info['releases']) && is_array($core_update_info['releases'])) {
      // Find the latest release that are higher than our current and is not beta/alpha/rc.
      foreach ($core_update_info['releases'] as $version => $release) {
        if ((version_compare($version, \Drupal::VERSION) > 0) && empty($release['version_extra'])) {
          $link = $core_update_info['link'] . '/releases/' . $version;
          $core_version_info = [
            '#type' => 'link',
            '#title' => $this->t('Version @current allows to upgrade but @new is available.', ['@current' => \Drupal::VERSION, '@new' => $version]),
            '#url' => Url::fromUri($link),
          ];
          $has_core_update = TRUE;
          break;
        }
      }
    }
    if (version_compare(\Drupal::VERSION, '8.8.0') >= 0) {
      if (!$has_core_update) {
        $class = 'no-known-error';
      }
      else {
        $class = 'known-warnings';
      }
    }
    else {
      $class = 'known-errors';
    }
    $build['data']['#rows'][] = [
      'class' => $class,
      'data' => [
        'requirement' => [
          'class' => 'requirement-label',
          'data' => $this->t('Drupal core should be 8.8.x or 8.9.x'),
        ],
        'status' => [
          'data' => $core_version_info,
          'class' => 'status-info',
        ],
      ]
    ];

    // Check PHP version.
    $version = PHP_VERSION;
    $build['data']['#rows'][] = [
      'class' => [(version_compare($version, '7.3.0') >= 0) ? 'no-known-error' : 'known-errors'],
      'data' => [
        'requirement' => [
          'class' => 'requirement-label',
          'data' => $this->t('PHP version should be at least 7.3.0'),
        ],
        'status' => [
          'data' => $this->t('Version @version', ['@version' => $version]),
          'class' => 'status-info',
        ],
      ]
    ];

    // Check database version.
    $database = \Drupal::database();
    $type = $database->databaseType();
    $version = $database->version();

    // MariaDB databases report as MySQL. Detect MariaDB separately based on code from
    // https://api.drupal.org/api/drupal/core%21lib%21Drupal%21Core%21Database%21Driver%21mysql%21Connection.php/function/Connection%3A%3AgetMariaDbVersionMatch/9.0.x
    // See also https://www.drupal.org/node/3119156 for test values.
    if ($type == 'mysql') {
      // MariaDB may prefix its version string with '5.5.5-', which should be
      // ignored.
      // @see https://github.com/MariaDB/server/blob/f6633bf058802ad7da8196d01fd19d75c53f7274/include/mysql_com.h#L42.
      $regex = '/^(?:5\\.5\\.5-)?(\\d+\\.\\d+\\.\\d+.*-mariadb.*)/i';
      preg_match($regex, $version, $matches);
      if (!empty($matches[1])) {
        $type = 'MariaDB';
        $version = $matches[1];
        $requirement = $this->t('When using MariaDB, minimum version is 10.3.7');
        if (version_compare($version, '10.3.7') >= 0) {
          $class = 'no-known-error';
        }
        elseif (version_compare($version, '10.1.0') >= 0) {
          $class = 'known-warnings';
          $requirement .= ' ' . $this->t('Alternatively, <a href=":driver">install the MariaDB 10.1 driver for Drupal 9</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql56']);
        }
        else {
          $class = 'known-errors';
          $requirement .= ' ' . $this->t('Once updated to at least 10.1, you can also <a href=":driver">install the MariaDB 10.1 driver for Drupal 9</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql56']);
        }
      }
      else {
        $type = 'MySQL or Percona Server';
        $requirement = $this->t('When using MySQL/Percona, minimum version is 5.7.8');
        if (version_compare($version, '5.7.8') >= 0) {
          $class = 'no-known-error';
        }
        elseif (version_compare($version, '5.6.0') >= 0) {
          $class = 'known-warnings';
          $requirement .= ' ' . $this->t('Alternatively, <a href=":driver">install the MySQL 5.6 driver for Drupal 9</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql56']);
        }
        else {
          $class = 'known-errors';
          $requirement .= ' ' . $this->t('Once updated to at least 5.6, you can also <a href=":driver">install the MySQL 5.6 driver for Drupal 9</a> for now.', [':driver' => 'https://www.drupal.org/project/mysql56']);
        }
      }
    }
    elseif ($type == 'pgsql') {
      $type = 'PostgreSQL';
      $requirement = $this->t('When using PostgreSQL, minimum version is 10 <a href=":trgm">with the pg_trgm extension</a> (The extension is not checked here)', [':trgm' => 'https://www.postgresql.org/docs/10/pgtrgm.html']);
      $class = (version_compare($version, '10') >= 0) ? 'no-known-error' : 'known-errors';
    }
    elseif ($type == 'sqlite') {
      $type = 'SQLite';
      $requirement = $this->t('When using SQLite, minimum version is 3.26');
      $class = (version_compare($version, '3.26') >= 0) ? 'no-known-error' : 'known-errors';
    }

    $build['data']['#rows'][] = [
      'class' => [$class],
      'data' => [
        'requirement' => [
          'class' => 'requirement-label',
          'data' => [
            '#type' => 'markup',
            '#markup' => $requirement
          ],
        ],
        'status' => [
          'data' => $type . ' ' . $version,
          'class' => 'status-info',
        ],
      ]
    ];

    // Check Apache. Logic is based on system_requirements() code.
    $request_object = \Drupal::request();
    $software = $request_object->server->get('SERVER_SOFTWARE');
    if (strpos($software, 'Apache') !== FALSE && preg_match('!^Apache/([\d\.]+) !', $software, $found)) {
      $version = $found[1];
      $class = [(version_compare($version, '2.4.7') >= 0) ? 'no-known-error' : 'known-errors'];
      $label = $this->t('Version @version', ['@version' => $version]);
    }
    else {
      $class = '';
      $label = $this->t('Version cannot be detected or not using Apache, check manually.');
    }
    $build['data']['#rows'][] = [
      'class' => $class,
      'data' => [
        'requirement' => [
          'class' => 'requirement-label',
          'data' => $this->t('When using Apache, minimum version is 2.4.7'),
        ],
        'status' => [
          'data' => $label,
          'class' => 'status-info',
        ],
      ]
    ];

    // Check Drush. We only detect site-local drush for now.
    if (class_exists('\\Drush\\Drush')) {
      $version = call_user_func('\\Drush\\Drush::getMajorVersion');
      $class = [(version_compare($version, '10') >= 0) ? 'no-known-error' : 'known-errors'];
      $label = $this->t('Version @version', ['@version' => $version]);
    }
    else {
      $class = '';
      $label = $this->t('Version cannot be detected, check manually.');
    }
    $build['data']['#rows'][] = [
      'class' => $class,
      'data' => [
        'requirement' => [
          'class' => 'requirement-label',
          'data' => $this->t('When using Drush, minimum version is 10'),
        ],
        'status' => [
          'data' => $label,
          'class' => 'status-info',
        ],
      ]
    ];

    return $build;
  }


  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $operations = [];
    $projects = $this->projectCollector->collectProjects();
    $submitted = $form_state->getValues();

    // It is not possible to make an HTTP request to this same webserver
    // if the host server is PHP itself, because it is single-threaded.
    // See https://www.php.net/manual/en/features.commandline.webserver.php
    $use_http = php_sapi_name() != 'cli-server';
    $php_server = !$use_http;
    if ($php_server) {
      // Log the selected processing method for project support purposes.
      $this->logger->notice('Processing projects without HTTP sandboxing because the built-in PHP webserver does not allow for that.');
    }
    else {
      // Attempt to do an HTTP request to the frontpage of this Drupal instance.
      // If that does not work then we'll not be able to process projects over
      // HTTP. Processing projects directly is less safe (in case of PHP fatal
      // errors the batch process may halt), but we have no other choice here
      // but to take a chance.
      list($error, $message, $data) = static::doHttpRequest('upgrade_status_request_test', 'upgrade_status_request_test');
      if (empty($data) || !is_array($data) || ($data['message'] != 'Request test success')) {
        $use_http = FALSE;
        $this->logger->notice('Processing projects without HTTP sandboxing. @error', ['@error' => $message]);
      }
    }

    if ($use_http) {
      // Log the selected processing method for project support purposes.
      $this->logger->notice('Processing projects with HTTP sandboxing.');
    }

    foreach (['custom', 'contrib'] as $type) {
      $states = ['uninstalled', 'installed'];
      foreach ($states as $state) {
        if (!empty($submitted[$type]['data'][$state])) {
          foreach($submitted[$type]['data'][$state] as $project => $checked) {
            if ($checked !== 0) {
              // If the checkbox was checked, add a batch operation.
              $operations[] = [
                static::class . '::parseProject',
                [$projects[$type][$project], $use_http]
              ];
            }
          }
        }
      }
    }
    if (!empty($operations)) {
      // Allow other modules to alter the operations to be run.
      $this->moduleHandler->alter('upgrade_status_operations', $operations, $form_state);
    }
    if (!empty($operations)) {
      $batch = [
        'title' => $this->t('Scanning projects'),
        'operations' => $operations,
      ];
      batch_set($batch);
    }
    else {
      $this->messenger()->addError('No projects selected to scan.');
    }
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function exportReportHTML(array &$form, FormStateInterface $form_state) {
    $selected = $form_state->getValues();
    $form_state->setResponse($this->exportReport($selected, 'html'));
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function exportReportASCII(array &$form, FormStateInterface $form_state) {
    $selected = $form_state->getValues();
    $form_state->setResponse($this->exportReport($selected, 'ascii'));
  }

  /**
   * Export generator.
   *
   * @param array $selected
   *   Selected projects from the form.
   * @param string $format
   *   The format of export to do: html or ascii.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object for this export.
   */
  public function exportReport(array $selected, string $format) {
    $extensions = [];
    $projects = $this->projectCollector->collectProjects();

    foreach (['custom', 'contrib'] as $type) {
      $states = ['uninstalled', 'installed'];
      foreach ($states as $state) {
        if (!empty($selected[$type]['data'][$state])) {
          foreach($selected[$type]['data'][$state] as $project => $checked) {
            if ($checked !== 0) {
              // If the checkbox was checked, add it to the list.
              $extensions[$type][$project] =
                $format == 'html' ?
                  $this->resultFormatter->formatResult($projects[$type][$project]) :
                  $this->resultFormatter->formatAsciiResult($projects[$type][$project]);
            }
          }
        }
      }
    }

    if (empty($extensions)) {
      $this->messenger()->addError('No projects selected to export.');
      return;
    }

    $build = [
      '#theme' => 'upgrade_status_'. $format . '_export',
      '#projects' => $extensions
    ];

    $fileDate = $this->resultFormatter->formatDateTime(0, 'html_datetime');
    $extension = $format == 'html' ? '.html' : '.txt';
    $filename = 'upgrade-status-export-' . $fileDate . $extension;

    $response = new Response($this->renderer->renderRoot($build));
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
    return $response;
  }

  /**
   * Batch callback to analyze a project.
   *
   * @param \Drupal\Core\Extension\Extension $extension
   *   The extension to analyze.
   * @param bool $use_http
   *   Whether to use HTTP to execute the processing or execute locally. HTTP
   *   processing could fail in some container setups. Local processing may
   *   fail due to timeout or memory limits.
   * @param array $context
   *   Batch context.
   */
  public static function parseProject(Extension $extension, $use_http, &$context) {
    $context['message'] = t('Analysis complete for @project.', ['@project' => $extension->getName()]);

    if (!$use_http) {
      \Drupal::service('upgrade_status.deprecation_analyzer')->analyze($extension);
      return;
    }

    // Do the HTTP request to run processing.
    list($error, $message, $data) = static::doHttpRequest($extension->getType(), $extension->getName());

    if ($error !== FALSE) {
      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $key_value */
      $key_value = \Drupal::service('keyvalue')->get('upgrade_status_scan_results');

      $result = [];
      $result['date'] = \Drupal::time()->getRequestTime();
      $result['data'] = [
        'totals' => [
          'errors' => 1,
          'file_errors' => 1,
          'upgrade_status_split' => [
            'warning' => 1,
          ]
        ],
        'files' => [],
      ];
      $result['data']['files'][$error] = [
        'errors' => 1,
        'messages' => [
          [
            'message' => $message,
            'line' => 0,
          ],
        ],
      ];

      $key_value->set($extension->getName(), json_encode($result));
    }

  }

  /**
   * Do an HTTP request with the type and machine name.
   *
   * @param string $type
   *   Type of the extension, it can be either 'module' or 'theme' or 'profile'.
   * @param string $project_machine_name
   *   The machine name of the project.
   *
   * @return array
   *   A three item array with any potential errors, the error message and the
   *   returned data as the third item. Either of them will be FALSE if they are
   *   not applicable. Data may also be NULL if response JSON decoding failed.
   */
  public static function doHttpRequest(string $type, string $project_machine_name) {
    $error = $message = $data = FALSE;

    // Prepare for a POST request to scan this project. The separate HTTP
    // request is used to separate any PHP errors found from this batch process.
    // We can store any errors and gracefully continue if there was any PHP
    // errors in parsing.
    $url = Url::fromRoute(
      'upgrade_status.analyze',
      [
        'type' => $type,
        'project_machine_name' => $project_machine_name
      ]
    );

    // Pass over authentication information because access to this functionality
    // requires administrator privileges.
    /** @var \Drupal\Core\Session\SessionConfigurationInterface $session_config */
    $session_config = \Drupal::service('session_configuration');
    $request = \Drupal::request();
    $session_options = $session_config->getOptions($request);
    // Unfortunately DrupalCI testbot does not have a domain that would normally
    // be considered valid for cookie setting, so we need to work around that
    // by manually setting the cookie domain in case there was none. What we
    // care about is we get actual results, and cookie on the host level should
    // suffice for that.
    $cookie_domain = empty($session_options['cookie_domain']) ? '.' . $request->getHost() : $session_options['cookie_domain'];
    $cookie_jar = new CookieJar();
    $cookie = new SetCookie([
      'Name' => $session_options['name'],
      'Value' => $request->cookies->get($session_options['name']),
      'Domain' => $cookie_domain,
      'Secure' => $session_options['cookie_secure'],
    ]);
    $cookie_jar->setCookie($cookie);
    $options = [
      'cookies' => $cookie_jar,
      'timeout' => 0,
    ];

    // Try a POST request with the session cookie included. We expect valid JSON
    // back. In case there was a PHP error before that, we log that.
    try {
      $response = \Drupal::httpClient()->post($url->setAbsolute()->toString(), $options);
      $data = json_decode((string) $response->getBody(), TRUE);
      if (!$data) {
        $error = 'PHP Fatal Error';
        $message = (string) $response->getBody();
      }
    }
    catch (\Exception $e) {
      $error = 'Scanning exception';
      $message = $e->getMessage();
    }

    return [$error, $message, $data];
  }

}
