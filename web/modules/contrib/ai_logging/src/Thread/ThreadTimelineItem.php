<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

/**
 * One item in a thread's timeline: a question, a call, or an answer.
 *
 * @internal
 */
class ThreadTimelineItem {

  /**
   * A user's question, shown before the call that first saw it.
   */
  public const QUESTION = 'question';

  /**
   * A call to the model.
   */
  public const CALL = 'call';

  /**
   * The answer that ended a turn, shown after the call that gave it.
   */
  public const ANSWER = 'answer';

  /**
   * Constructs a ThreadTimelineItem.
   *
   * @param string $type
   *   One of the QUESTION, CALL or ANSWER constants.
   * @param \Drupal\ai_logging\Thread\ThreadCall $call
   *   The call, or for a question the call that first saw it, or for an
   *   answer the call that gave it.
   * @param string $text
   *   The question or answer's text. Empty for a call.
   */
  public function __construct(
    public readonly string $type,
    public readonly ThreadCall $call,
    public readonly string $text = '',
  ) {}

  /**
   * Gets the HTML ID of the item's element, for the contents to link to.
   *
   * @return string
   *   The ID.
   */
  public function getElementId(): string {
    $id = $this->call->entry->id();
    return match ($this->type) {
      static::QUESTION => 'ai-log-question-' . $id,
      static::ANSWER => 'ai-log-answer-' . $id,
      default => 'ai-log-' . $id,
    };
  }

}
