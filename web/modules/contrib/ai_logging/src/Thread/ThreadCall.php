<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

use Drupal\ai_logging\AiLogInterface;

/**
 * One logged AI call in a thread, with what it got back from the model.
 *
 * @internal
 */
class ThreadCall {

  /**
   * The model only called tools.
   */
  public const KIND_TOOL_CALLS = 'tool_calls';

  /**
   * The model returned a structured (JSON) result.
   */
  public const KIND_STRUCTURED = 'structured';

  /**
   * The model answered in text.
   */
  public const KIND_TEXT = 'text';

  /**
   * Only the provider's raw output was logged.
   */
  public const KIND_RAW = 'raw';

  /**
   * Nothing was logged of the response.
   */
  public const KIND_NONE = 'none';

  /**
   * Constructs a ThreadCall.
   *
   * @param \Drupal\ai_logging\AiLogInterface $entry
   *   The log entry.
   * @param int $number
   *   The call's position in the thread, from 1.
   * @param string $kind
   *   What the call got back, one of the KIND_* constants.
   * @param string $text
   *   The reply text, or the raw output when only that was logged.
   * @param array<int, array{name: string, arguments: array}> $toolCalls
   *   The tools called, when the kind is KIND_TOOL_CALLS.
   * @param string[] $tags
   *   All of the call's request tags.
   * @param string[] $purposeTags
   *   The request tags that say what the call was for.
   * @param int $elapsed
   *   Seconds since the thread's first call.
   * @param string|null $newPromptText
   *   What the prompt added to the previous call made for the same purpose,
   *   or NULL if it doesn't continue it and should be read whole.
   */
  public function __construct(
    public readonly AiLogInterface $entry,
    public readonly int $number,
    public readonly string $kind,
    public readonly string $text,
    public readonly array $toolCalls,
    public readonly array $tags,
    public readonly array $purposeTags,
    public readonly int $elapsed,
    public readonly ?string $newPromptText,
  ) {}

  /**
   * Checks whether the call answered in text, so could end a turn.
   *
   * @return bool
   *   TRUE if the model answered in text.
   */
  public function isTextAnswer(): bool {
    return $this->kind === static::KIND_TEXT;
  }

  /**
   * Gets when the call was made.
   *
   * @return int
   *   The Unix timestamp.
   */
  public function getCreated(): int {
    return (int) $this->entry->get('created')->value;
  }

}
