<?php

declare(strict_types=1);

namespace Drupal\ai_logging\Thread;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ai_logging\AiLogInterface;
use Drupal\ai_logging\ResponseTextFormatter;

/**
 * Arranges a thread's calls into turns of questions, calls and answers.
 *
 * Each turn starts at the call that first sees a new user question, and
 * ends with the answer to it: the last call in the turn that answered in
 * text. Calls that put their own instructions in a user message, such as a
 * classifier, are told apart by the primary tag pattern: only a call tagged
 * as the thread's agent can ask a question.
 *
 * @internal
 */
class ThreadTimelineBuilder {

  /**
   * Constructs a ThreadTimelineBuilder.
   *
   * @param \Drupal\ai_logging\Thread\ThreadRepository $threadRepository
   *   The thread repository.
   * @param \Drupal\ai_logging\ResponseTextFormatter $responseTextFormatter
   *   The reply text formatter.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected readonly ThreadRepository $threadRepository,
    protected readonly ResponseTextFormatter $responseTextFormatter,
    protected readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Builds a thread's timeline.
   *
   * @param \Drupal\ai_logging\AiLogInterface[] $entries
   *   The thread's log entries the user may see, oldest first.
   * @param string $thread_id
   *   The thread's ID.
   *
   * @return \Drupal\ai_logging\Thread\ThreadTimelineItem[]
   *   The timeline, in order.
   */
  public function build(array $entries, string $thread_id): array {
    $pattern = trim((string) $this->configFactory->get('ai_logging.settings')->get('thread_primary_tag_pattern'));
    $entries = array_values($entries);
    $start = $entries ? (int) $entries[0]->get('created')->value : 0;

    // Each call's prompt is compared with the previous call made for the
    // same purpose, which for an agent is its own previous turn.
    $previous_prompts = [];
    $question = NULL;
    $turns = [];
    $turn = ['question' => NULL, 'calls' => []];
    foreach ($entries as $index => $entry) {
      $tags = $this->getTags($entry);
      $purpose_tags = $this->threadRepository->getPurposeTags($tags, (string) $entry->get('operation_type')->value, $thread_id);
      $purpose = implode(',', $purpose_tags);
      $prompt = (string) $entry->get('prompt')->value;
      $call = $this->buildCall($entry, $index + 1, $start, $tags, $purpose_tags, $previous_prompts[$purpose] ?? '');
      $previous_prompts[$purpose] = $prompt;

      $asked = Transcript::lastUserMessage($prompt);
      if ($asked !== NULL && $asked !== $question && ($pattern === '' || TagPattern::matches($tags, $pattern))) {
        $turns[] = $turn;
        $turn = [
          'question' => new ThreadTimelineItem(ThreadTimelineItem::QUESTION, $call, $asked),
          'calls' => [],
        ];
        $question = $asked;
      }
      $turn['calls'][] = $call;
    }
    $turns[] = $turn;

    $items = [];
    foreach ($turns as $turn) {
      if ($turn['question']) {
        $items[] = $turn['question'];
      }
      $answer = $this->findAnswer($turn['calls']);
      foreach ($turn['calls'] as $call) {
        $items[] = new ThreadTimelineItem(ThreadTimelineItem::CALL, $call);
        // Shown straight after the call it came from, before any later
        // calls of the turn, such as checks on the next question.
        if ($call === $answer) {
          $items[] = new ThreadTimelineItem(ThreadTimelineItem::ANSWER, $call, $call->text);
        }
      }
    }
    return $items;
  }

  /**
   * Finds the call that answered a turn: its last text response.
   *
   * @param \Drupal\ai_logging\Thread\ThreadCall[] $calls
   *   The turn's calls, oldest first.
   *
   * @return \Drupal\ai_logging\Thread\ThreadCall|null
   *   The call, or NULL if none answered in text, e.g. it only called tools.
   */
  protected function findAnswer(array $calls): ?ThreadCall {
    foreach (array_reverse($calls) as $call) {
      if ($call->isTextAnswer()) {
        return $call;
      }
    }
    return NULL;
  }

  /**
   * Builds one call of the thread.
   *
   * @param \Drupal\ai_logging\AiLogInterface $entry
   *   The log entry.
   * @param int $number
   *   The call's position in the thread, from 1.
   * @param int $start
   *   When the thread's first call was made.
   * @param string[] $tags
   *   The call's request tags.
   * @param string[] $purpose_tags
   *   The tags that say what the call was for.
   * @param string $previous_prompt
   *   The prompt of the previous call made for the same purpose, if any.
   *
   * @return \Drupal\ai_logging\Thread\ThreadCall
   *   The call.
   */
  protected function buildCall(AiLogInterface $entry, int $number, int $start, array $tags, array $purpose_tags, string $previous_prompt): ThreadCall {
    $response_text = (string) $entry->get('response_text')->value;
    $tool_calls = $this->responseTextFormatter->getToolCalls($response_text);
    if ($tool_calls !== NULL) {
      $kind = ThreadCall::KIND_TOOL_CALLS;
      $text = '';
    }
    elseif ($response_text !== '') {
      $kind = $this->responseTextFormatter->format($response_text)['structured'] ? ThreadCall::KIND_STRUCTURED : ThreadCall::KIND_TEXT;
      $text = $response_text;
    }
    else {
      $text = (string) $entry->get('output_text')->value;
      $kind = $text !== '' ? ThreadCall::KIND_RAW : ThreadCall::KIND_NONE;
    }

    $prompt = (string) $entry->get('prompt')->value;
    return new ThreadCall(
      entry: $entry,
      number: $number,
      kind: $kind,
      text: $text,
      toolCalls: $tool_calls ?? [],
      tags: $tags,
      purposeTags: $purpose_tags,
      elapsed: (int) $entry->get('created')->value - $start,
      newPromptText: $previous_prompt !== '' ? PromptDiff::newText($previous_prompt, $prompt) : NULL,
    );
  }

  /**
   * Gets a log entry's tags.
   *
   * @param \Drupal\ai_logging\AiLogInterface $entry
   *   The log.
   *
   * @return string[]
   *   The tags.
   */
  protected function getTags(AiLogInterface $entry): array {
    $tags = [];
    foreach ($entry->get('tags') as $item) {
      $tags[] = (string) $item->value;
    }
    return $tags;
  }

}
