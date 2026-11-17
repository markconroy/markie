<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

/**
 * Finds what a call's prompt added to the previous call's.
 *
 * An agent sends the whole conversation again on every turn, so each logged
 * prompt repeats the one before it plus the latest messages. Showing only
 * what was added makes a thread readable turn by turn.
 *
 * @internal
 */
class PromptDiff {

  /**
   * The shortest shared start that counts as a continuation, in characters.
   */
  public const MINIMUM_SHARED_LENGTH = 200;

  /**
   * Gets the text a prompt added to the previous one.
   *
   * @param string $previous
   *   The previous call's prompt.
   * @param string $current
   *   This call's prompt.
   *
   * @return string|null
   *   The added text, starting at the beginning of a line, or NULL if this
   *   prompt neither continues the previous one nor shares a long enough
   *   start with it (a different conversation or a rewritten prompt), so the
   *   whole prompt should be read instead.
   */
  public static function newText(string $previous, string $current): ?string {
    $length = min(strlen($previous), strlen($current));
    $shared = 0;
    // Compare in blocks first, then byte by byte, so long prompts stay cheap.
    while ($shared + 4096 <= $length && substr($previous, $shared, 4096) === substr($current, $shared, 4096)) {
      $shared += 4096;
    }
    while ($shared < $length && $previous[$shared] === $current[$shared]) {
      $shared++;
    }
    // A short previous prompt still counts when this one simply continues
    // it, as on an agent's second turn.
    $continues = $shared === strlen($previous);
    if ((!$continues && $shared < static::MINIMUM_SHARED_LENGTH) || $shared === strlen($current)) {
      return NULL;
    }
    // Start at the beginning of the line the difference begins on.
    $line_start = strrpos(substr($current, 0, $shared), "\n");
    $start = $line_start === FALSE ? 0 : $line_start + 1;
    return ltrim(substr($current, $start), "\n");
  }

}
