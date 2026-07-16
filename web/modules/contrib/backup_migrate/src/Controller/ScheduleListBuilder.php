<?php

namespace Drupal\backup_migrate\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\backup_migrate\Entity\Schedule;
use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a listing of Schedule entities.
 */
class ScheduleListBuilder extends ConfigEntityListBuilder {

  /**
   * Constructs a ScheduleListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected readonly TimeInterface $time,
    protected readonly DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('datetime.time'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Schedule Name');
    $header['enabled'] = $this->t('Enabled');
    $header['period'] = $this->t('Frequency');
    $header['last_run'] = $this->t('Last Run');
    $header['next_run'] = $this->t('Next Run');
    $header['keep'] = $this->t('Keep');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   *
   * ScheduleListBuilder save implementation requires instance of Schedule.
   * Signature enforced by EntityListBuilder.
   *
   * @throw InvalidArgumentException
   */
  public function buildRow(EntityInterface $entity) {
    if (!$entity instanceof Schedule) {
      throw new \Exception();
    }
    $row['label'] = $entity->label();
    $row['enabled'] = $entity->get('enabled') ? $this->t('Yes') : $this->t('No');
    $row['period'] = $entity->getPeriodFormatted();

    $row['last_run'] = $this->t('Never');
    $time = $this->time;
    if ($last_run = $entity->getLastRun()) {
      $row['last_run'] = $this->dateFormatter->format($last_run, 'small');
      $row['last_run'] .= ' (' . $this->t('@time ago', ['@time' => $this->dateFormatter->formatInterval($time->getRequestTime() - $last_run)]) . ')';
    }

    $row['next_run'] = $this->t('Not Scheduled');
    if (!$entity->get('enabled')) {
      $row['next_run'] = $this->t('Disabled');
    }
    elseif ($next_run = $entity->getNextRun()) {
      $interval = $this->dateFormatter->formatInterval(abs($next_run - $time->getRequestTime()));
      if ($next_run > $time->getRequestTime()) {
        $row['next_run'] = $this->dateFormatter->format($next_run, 'small');
        $row['next_run'] .= ' (' . $this->t('in @time', ['@time' => $interval]) . ')';
      }
      else {
        $row['next_run'] = $this->t('Next cron run');
        if ($last_run) {
          $row['next_run'] .= ' (' . $this->t('was due @time ago', ['@time' => $interval]) . ')';
        }
      }
    }

    // The "keep" option isn't required, so this may not be a positive integer.
    // @todo Add some extra validation to the form so this doesn't save 0 to
    // the field, especially when the user submits a non-numeric value.
    $keep = $entity->get('keep');
    if (is_numeric($keep) && (int) $keep > 0) {
      $row['keep'] = $this->formatPlural($keep, 'Last 1 backup', 'Last @count backups');
    }
    else {
      $row['keep'] = $this->t('All backups');
    }

    return $row + parent::buildRow($entity);
  }

}
