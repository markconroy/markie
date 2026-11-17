<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

/**
 * Reads the chat transcripts stored as log entries' prompts.
 *
 * A transcript writes each message as its role on a line of its own,
 * followed by its text, e.g. "user\nA question?\nassistant\nAn answer.\n".
 * A tool result's role names the tool, e.g. "tool (search)".
 *
 * @see \Drupal\ai_logging\EventSubscriber\LogPostRequestEventSubscriber::getPromptText()
 *
 * @internal
 */
class Transcript {

  /**
   * Matches a line holding only a message's role.
   */
  public const ROLE_PATTERN = '/^(user|assistant|system|tool)( \(.+\))?$/';

  /**
   * Gets the text of the last user message in a transcript.
   *
   * In an agent's prompt this is the question it is working on, however
   * many tool calls and results follow it.
   *
   * @param string $transcript
   *   The transcript.
   *
   * @return string|null
   *   The message, trimmed, or NULL if there is no user message.
   */
  public static function lastUserMessage(string $transcript): ?string {
    $message = NULL;
    $lines = NULL;
    foreach (explode("\n", $transcript) as $line) {
      if (preg_match(self::ROLE_PATTERN, $line) === 1) {
        if ($lines !== NULL) {
          $message = $lines;
        }
        $lines = $line === 'user' ? [] : NULL;
        continue;
      }
      if ($lines !== NULL) {
        $lines[] = $line;
      }
    }
    if ($lines !== NULL) {
      $message = $lines;
    }
    if ($message === NULL) {
      return NULL;
    }
    $text = trim(implode("\n", $message));
    return $text === '' ? NULL : $text;
  }

}
