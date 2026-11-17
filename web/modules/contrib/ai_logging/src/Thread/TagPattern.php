<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

/**
 * Matches request tags against a LIKE pattern, as the database would.
 *
 * @internal
 */
class TagPattern {

  /**
   * Checks whether any of a log entry's tags matches a LIKE pattern.
   *
   * @param string[] $tags
   *   The log entry's tags.
   * @param string $pattern
   *   The pattern, as for the thread_primary_tag_pattern setting: % matches
   *   any run of characters and _ any one character. Case is ignored, as
   *   the database's LIKE usually does.
   *
   * @return bool
   *   TRUE if a tag matches.
   */
  public static function matches(array $tags, string $pattern): bool {
    // A run of % matches no more than one does, and would only make the
    // regular expression backtrack further on tags that don't match.
    $pattern = (string) preg_replace('/%+/', '%', $pattern);
    $regex = '/^' . strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']) . '$/is';
    foreach ($tags as $tag) {
      if (preg_match($regex, (string) $tag) === 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
