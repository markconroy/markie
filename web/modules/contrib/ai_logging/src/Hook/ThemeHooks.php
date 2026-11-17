<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook implementations for AI Logging.
 *
 * @internal
 */
class ThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'ai_logging_thread_contents' => [
        'variables' => [
          'items' => [],
          'totals' => NULL,
        ],
      ],
      'ai_logging_thread_call_summary' => [
        'variables' => [
          'title' => NULL,
          'purpose_tags' => [],
          'meta' => NULL,
          'tool_calls' => [],
          'preview' => NULL,
        ],
      ],
      'ai_logging_thread_highlight' => [
        'variables' => [
          'id' => NULL,
          'kind' => NULL,
          'title' => NULL,
          'text' => NULL,
          'markdown' => FALSE,
        ],
      ],
    ];
  }

}
