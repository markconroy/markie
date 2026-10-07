<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

/**
 * Resolves the label shown for a conversation thread.
 *
 * To label threads differently, decorate or replace the
 * ai_logging.thread_label_resolver service.
 */
interface ThreadLabelResolverInterface {

  /**
   * Gets the label of a thread.
   *
   * @param string $thread_id
   *   The thread ID, without its tag prefix.
   *
   * @return string|null
   *   The label, or NULL if none can be found.
   */
  public function getLabel(string $thread_id): ?string;

}
