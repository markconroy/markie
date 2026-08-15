<?php

namespace Drupal\backup_migrate\Controller;

use Drupal\backup_migrate\Core\Destination\ListableDestinationInterface;
use Drupal\backup_migrate\Drupal\Destination\DrupalBrowserDownloadDestination;
use Drupal\backup_migrate\Entity\Destination;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\Core\Utility\TableSort;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides the backup controller class.
 *
 * @package Drupal\backup_migrate\Controller
 */
class BackupController extends ControllerBase {

  /**
   * Stores the value.
   *
   * @var \Drupal\backup_migrate\Core\Destination\DestinationInterface The destination
   */
  public $destination;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a BackupController object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RendererInterface $renderer, DateFormatterInterface $date_formatter, RequestStack $request_stack) {
    $this->renderer = $renderer;
    $this->dateFormatter = $date_formatter;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('date.formatter'),
      $container->get('request_stack')
    );
  }

  /**
   * Lists all backups.
   */
  public function listAll() {
    $storage = $this->entityTypeManager()
      ->getStorage('backup_migrate_destination');

    $out = [];
    foreach ($storage->getQuery()->accessCheck(FALSE)->execute() as $key) {
      $entity = $storage->load($key);
      $destination = $entity->getObject();
      $label = $destination->confGet('name');

      $out[$key] = [
        'title' => [
          '#markup' => '<h2>' . $this->t('Most recent backups in %dest', ['%dest' => $label]) . '</h2>',
        ],
        'list' => $this->listDestinationBackups($destination, $key, 5),
      ];
      // Add the more link.
      if ($entity->access('backups') && $entity->hasLinkTemplate('backups')) {
        $out[$key]['link'] = $entity->toLink(
          $this->t('View all backups in %dest', ['%dest' => $label]), 'backups'
        )->toRenderable();
      }

    }
    return $out;
  }

  /**
   * Get the title for the listing page of a destination entity.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   *   The backup and migrate destination.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translated string.
   */
  public function listDestinationEntityBackupsTitle(Destination $backup_migrate_destination) {
    return $this->t('Backups in @destination_name',
      ['@destination_name' => $backup_migrate_destination->label()]);
  }

  /**
   * List the backups in the given destination.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   *   The backup and migrate destination.
   *
   * @return mixed
   *   The return value.
   */
  public function listDestinationEntityBackups(Destination $backup_migrate_destination) {
    $destination = $backup_migrate_destination->getObject();
    return $this->listDestinationBackups($destination,
      $backup_migrate_destination->id());
  }

  /**
   * List the backups in the given destination.
   *
   * @param \Drupal\backup_migrate\Core\Destination\ListableDestinationInterface $destination
   *   The destination.
   * @param string $backup_migrate_destination_id
   *   The backup and migrate destination ID.
   * @param int $count
   *   The maximum number of items to return.
   *
   * @return mixed
   *   The return value.
   */
  public function listDestinationBackups(ListableDestinationInterface $destination, $backup_migrate_destination_id, $count = NULL) {
    // Get a sorted list of files.
    $rows = [];
    $header = [
      [
        'data' => $this->t('Name'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'name',
      ],
      [
        'data' => $this->t('Date'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'datestamp',
        'sort' => 'asc',
      ],
      [
        'data' => $this->t('Size'),
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
        'field' => 'filesize',
        'sort' => 'desc',
      ],
      [
        'data' => $this->t('Operations'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];

    $request = $this->requestStack->getCurrentRequest();
    $order = TableSort::getOrder($header, $request);
    $sort = TableSort::getSort($header, $request);
    $php_sort = $sort == 'desc' ? SORT_DESC : SORT_ASC;

    $backups = $destination->queryFiles([], $order['sql'], $php_sort, $count);

    foreach ($backups as $backup_id => $backup) {
      $col['description'] = [
        '#markup' => '<div title="' . $backup->getFullName() . '" class="backup-migrate-description">' . $backup->getFullName() . '</div>',
      ];

      if (!empty($backup->getMeta('description'))) {
        $col['description']['#markup'] .= ' <div title="' . $backup->getMeta('description') . '" class="backup-migrate-description">' . $backup->getMeta('description') . '</div>';
      }
      $format_data = !class_exists(ByteSizeMarkup::class) ?
      // @phpstan-ignore-next-line as it requires for backward compatibility.
      format_size($backup->getMeta('filesize')) :
      ByteSizeMarkup::create($backup->getMeta('filesize'));
      $rows[] = [
        'data' => [
          // Cells.
          $this->renderer->render($col['description']),
          $this->dateFormatter->format($backup->getMeta('datestamp')),
          $format_data,
          [
            'data' => [
              '#type' => 'operations',
              '#links' => [
                'restore' => [
                  'title' => $this->t('Restore'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_restore',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
                'download' => [
                  'title' => $this->t('Download'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_download',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
                'delete' => [
                  'title' => $this->t('Delete'),
                  'url' => Url::fromRoute(
                    'entity.backup_migrate_destination.backup_delete',
                    [
                      'backup_migrate_destination' => $backup_migrate_destination_id,
                      'backup_id' => $backup_id,
                    ]
                  ),
                ],
              ],
            ],
          ],
        ],
      ];
    }

    $build['backups_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('There are no backups in this destination.'),
    ];

    return $build;
  }

  /**
   * Download a backup via the browser.
   *
   * @param \Drupal\backup_migrate\Entity\Destination $backup_migrate_destination
   *   The backup and migrate destination.
   * @param string $backup_id
   *   The backup ID.
   */
  public function download(Destination $backup_migrate_destination, $backup_id) {
    $destination = $backup_migrate_destination->getObject();
    $file = $destination->getFile($backup_id);
    $file = $destination->loadFileForReading($file);

    $browser = new DrupalBrowserDownloadDestination();
    $browser->saveFile($file);

    return $this->redirect('backup_migrate.backups');
  }

}
