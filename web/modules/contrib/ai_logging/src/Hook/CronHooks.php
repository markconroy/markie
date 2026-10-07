<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\State\StateInterface;
use Drupal\ai_logging\Cron\AiLogPruning;

/**
 * Cron hook implementations for AI Logging.
 *
 * @internal
 */
class CronHooks {

  /**
   * Constructs a new CronHooks object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\ai_logging\Cron\AiLogPruning $pruning
   *   The AI log pruning service.
   */
  public function __construct(
    protected readonly TimeInterface $time,
    protected readonly StateInterface $state,
    protected readonly AiLogPruning $pruning,
  ) {
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Run once per day.
    $current_time = $this->time->getRequestTime();
    $last_run = $this->state->get('ai_logging.last_cron_run', 0);
    $interval = 86400;

    if (($current_time - $last_run) >= $interval) {
      $this->pruning->pruneLogs();
      $this->state->set('ai_logging.last_cron_run', $current_time);
    }
  }

}
