<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Labels a thread with the first user message of its first chat call.
 *
 * Helper calls such as guardrails can join a thread and be logged before its
 * first real turn. When the "primary turn tag pattern" setting is not empty,
 * the earliest chat call with a tag matching it is used, falling back to the
 * earliest chat call when no call matches.
 */
class DefaultThreadLabelResolver implements ThreadLabelResolverInterface {

  /**
   * The maximum length of a label, in characters.
   */
  protected int $maxLength = 120;

  /**
   * Constructs a DefaultThreadLabelResolver object.
   *
   * @param \Drupal\ai_logging\Thread\ThreadRepository $threadRepository
   *   The thread repository.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected ThreadRepository $threadRepository,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(string $thread_id): ?string {
    $pattern = trim((string) $this->configFactory->get('ai_logging.settings')->get('thread_primary_tag_pattern'));
    $prompt = NULL;
    if ($pattern !== '') {
      $prompt = $this->threadRepository->loadFirstChatPrompt($thread_id, $pattern);
    }
    $prompt ??= $this->threadRepository->loadFirstChatPrompt($thread_id);
    if ($prompt === NULL) {
      return NULL;
    }
    $message = $this->extractFirstUserMessage($prompt);
    if ($message === NULL) {
      return NULL;
    }
    return Unicode::truncate($message, $this->maxLength, TRUE, TRUE);
  }

  /**
   * Extracts the first user message from a logged chat prompt.
   *
   * The prompt is logged with ChatInput::toString(), which writes each
   * message as its role and its text on separate lines. If that format
   * changes upstream, this method needs to change with it.
   *
   * @param string $prompt
   *   The logged prompt.
   *
   * @return string|null
   *   The text of the first user message, or NULL if there is none.
   *
   * @see \Drupal\ai\OperationType\Chat\ChatInput::toString()
   */
  protected function extractFirstUserMessage(string $prompt): ?string {
    if (!preg_match('/(?:^|\n)user\n(.*?)(?=\n(?:user|assistant|tool|system)\n|\n?$)/s', $prompt, $matches)) {
      return NULL;
    }
    $message = trim(preg_replace('/\s+/', ' ', $matches[1]));
    return $message === '' ? NULL : $message;
  }

}
